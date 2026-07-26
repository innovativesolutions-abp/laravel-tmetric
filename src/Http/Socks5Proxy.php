<?php

namespace InnovativeSolutions\TMetric\Http;

use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;

final readonly class Socks5Proxy
{
    private function __construct(
        #[\SensitiveParameter]
        private string $uri,
    ) {}

    public static function fromUri(#[\SensitiveParameter] string $uri): self
    {
        if (! extension_loaded('curl') || ! defined('CURLPROXY_SOCKS5_HOSTNAME')) {
            throw new ConfigurationException('TMetric socks5h proxy support requires the PHP cURL extension.');
        }

        if ($uri === '' || trim($uri) !== $uri || preg_match('/[\x00-\x20\x7f]/', $uri) === 1) {
            throw new ConfigurationException('TMetric proxy must be a valid socks5h URI.');
        }

        $parts = parse_url($uri);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'socks5h'
            || ! is_string($parts['host'] ?? null)
            || $parts['host'] === ''
            || ! isset($parts['port'])
            || $parts['port'] < 1
            || $parts['port'] > 65535
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '')
        ) {
            throw new ConfigurationException(
                'TMetric proxy must use socks5h with a host and port, without credentials, path, query, or fragment.',
            );
        }

        return new self($uri);
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new \LogicException('TMetric proxy configuration cannot be serialized.');
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['uri' => '[REDACTED]'];
    }
}
