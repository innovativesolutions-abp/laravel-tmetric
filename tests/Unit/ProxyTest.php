<?php

namespace InnovativeSolutions\TMetric\Tests\Unit;

use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Proxy;
use InnovativeSolutions\TMetric\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ProxyTest extends TestCase
{
    #[DataProvider('validProxyProvider')]
    public function test_it_accepts_supported_credential_free_proxies(string $uri): void
    {
        self::assertSame($uri, Proxy::fromUri($uri)->uri());
    }

    public static function validProxyProvider(): array
    {
        return [
            'remote DNS SOCKS5' => ['socks5h://private-proxy.test:1080'],
            'HTTP CONNECT' => ['http://private-proxy.test:8890'],
        ];
    }

    public static function invalidProxyProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => [' http://proxy.test:8890'],
            'https' => ['https://proxy.test:8890'],
            'local DNS socks' => ['socks5://proxy.test:1080'],
            'unknown scheme' => ['ftp://proxy.test:8890'],
            'missing host' => ['http://:8890'],
            'missing port' => ['http://proxy.test'],
            'zero port' => ['http://proxy.test:0'],
            'out of range port' => ['http://proxy.test:65536'],
            'credentials' => ['http://user:secret@proxy.test:8890'],
            'path' => ['http://proxy.test:8890/path'],
            'query' => ['http://proxy.test:8890?secret=value'],
            'fragment' => ['http://proxy.test:8890#fragment'],
            'control character' => ["http://proxy.test:8890\n"],
        ];
    }

    #[DataProvider('invalidProxyProvider')]
    public function test_it_rejects_invalid_or_unsafe_proxy_uris(string $uri): void
    {
        try {
            Proxy::fromUri($uri);
            self::fail('Expected proxy configuration failure.');
        } catch (ConfigurationException $exception) {
            if ($uri !== '') {
                self::assertStringNotContainsString($uri, $exception->getMessage());
            }
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    public function test_connection_configuration_uses_the_generic_proxy_value(): void
    {
        $connection = ConnectionConfig::fromArray('default', [
            ...config('tmetric.connections.default'),
            'proxy' => 'http://private-proxy.test:8890',
        ]);

        self::assertInstanceOf(Proxy::class, $connection->proxy());
        self::assertSame('http://private-proxy.test:8890', $connection->proxy()?->uri());
    }

    public function test_proxy_and_connection_debug_output_are_redacted_and_not_serializable(): void
    {
        $proxy = Proxy::fromUri('http://proxy-secret.test:8890');
        $connection = ConnectionConfig::fromArray('default', [
            ...config('tmetric.connections.default'),
            'proxy' => $proxy->uri(),
        ]);

        foreach ([$proxy, $connection] as $value) {
            $debug = print_r($value, true);

            self::assertStringContainsString('[REDACTED]', $debug);
            self::assertStringNotContainsString('proxy-secret.test', $debug);
            self::assertStringNotContainsString('synthetic-secret-token', $debug);

            try {
                serialize($value);
                self::fail('Expected serialization to be blocked.');
            } catch (\LogicException $exception) {
                self::assertStringNotContainsString('proxy-secret.test', $exception->getMessage());
                self::assertStringNotContainsString('synthetic-secret-token', $exception->getMessage());
            }
        }
    }
}
