<?php

namespace InnovativeSolutions\TMetric\Http;

use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;

final readonly class ConnectionConfig
{
    public function __construct(
        public string $name,
        #[\SensitiveParameter]
        private string $token,
        public ?string $accountId,
        public bool $legacyEnabled,
        public string $v3BaseUrl,
        public string $legacyBaseUrl,
        public int $timeout,
        public int $connectTimeout,
        public int $maxAttempts,
        public int $maxRetryDelaySeconds,
        private ?Proxy $proxy,
    ) {
        if ($this->token === '') {
            throw new ConfigurationException("TMetric connection [{$this->name}] has no token.");
        }

        foreach ([$this->v3BaseUrl, $this->legacyBaseUrl] as $url) {
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string(parse_url($url, PHP_URL_HOST))) {
                throw new ConfigurationException('TMetric base URLs must use HTTPS.');
            }
        }

        if ($this->maxAttempts < 1 || $this->maxAttempts > 10) {
            throw new ConfigurationException('TMetric max_attempts must be between 1 and 10.');
        }
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(string $name, #[\SensitiveParameter] array $config): self
    {
        return new self(
            name: $name,
            token: (string) ($config['token'] ?? ''),
            accountId: isset($config['account_id']) ? (string) $config['account_id'] : null,
            legacyEnabled: (bool) ($config['legacy_enabled'] ?? false),
            v3BaseUrl: rtrim((string) ($config['v3_base_url'] ?? 'https://app.tmetric.com/api/v3'), '/'),
            legacyBaseUrl: rtrim((string) ($config['legacy_base_url'] ?? 'https://app.tmetric.com'), '/'),
            timeout: max(1, (int) ($config['timeout'] ?? 15)),
            connectTimeout: max(1, (int) ($config['connect_timeout'] ?? 5)),
            maxAttempts: (int) ($config['max_attempts'] ?? 3),
            maxRetryDelaySeconds: max(0, (int) ($config['max_retry_delay_seconds'] ?? 30)),
            proxy: self::proxyFromConfig($config),
        );
    }

    public function token(): string
    {
        return $this->token;
    }

    public function proxy(): ?Proxy
    {
        return $this->proxy;
    }

    /** @param array<string, mixed> $config */
    private static function proxyFromConfig(#[\SensitiveParameter] array $config): ?Proxy
    {
        $proxy = $config['proxy'] ?? null;

        if ($proxy === null || $proxy === '') {
            return null;
        }

        if (! is_string($proxy)) {
            throw new ConfigurationException('TMetric proxy must be a valid socks5h or HTTP URI.');
        }

        return Proxy::fromUri($proxy);
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new \LogicException('TMetric connection configuration cannot be serialized.');
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'token' => '[REDACTED]',
            'accountId' => $this->accountId,
            'legacyEnabled' => $this->legacyEnabled,
            'v3BaseUrl' => $this->v3BaseUrl,
            'legacyBaseUrl' => $this->legacyBaseUrl,
            'proxy' => $this->proxy === null ? null : '[REDACTED]',
        ];
    }
}
