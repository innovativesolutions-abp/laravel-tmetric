<?php

namespace InnovativeSolutions\TMetric\Http;

use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;

final readonly class Proxy
{
    private function __construct(
        #[\SensitiveParameter]
        private string $uri,
    ) {}

    public static function fromUri(#[\SensitiveParameter] string $uri): self
    {
        if ($uri === '' || trim($uri) !== $uri || preg_match('/[\x00-\x20\x7f]/', $uri) === 1) {
            throw new ConfigurationException('TMetric proxy must be a valid socks5h or HTTP URI.');
        }

        $parts = parse_url($uri);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (
            ! is_array($parts)
            || ! in_array($scheme, ['http', 'socks5h'], true)
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
                'TMetric proxy must use socks5h or http with a host and port, without credentials, path, query, or fragment.',
            );
        }

        if (
            $scheme === 'socks5h'
            && (! extension_loaded('curl') || ! defined('CURLPROXY_SOCKS5_HOSTNAME'))
        ) {
            throw new ConfigurationException('TMetric socks5h proxy support requires the PHP cURL extension.');
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
