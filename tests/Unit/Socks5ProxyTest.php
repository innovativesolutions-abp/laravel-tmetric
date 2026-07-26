<?php

namespace InnovativeSolutions\TMetric\Tests\Unit;

use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Socks5Proxy;
use InnovativeSolutions\TMetric\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class Socks5ProxyTest extends TestCase
{
    public function test_it_accepts_only_remote_dns_socks5_proxy_with_host_and_port(): void
    {
        $proxy = Socks5Proxy::fromUri('socks5h://tmetric-egress.test:1080');

        self::assertSame('socks5h://tmetric-egress.test:1080', $proxy->uri());
    }

    public static function invalidProxyProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => [' socks5h://proxy.test:1080'],
            'local DNS socks' => ['socks5://proxy.test:1080'],
            'http' => ['http://proxy.test:1080'],
            'https' => ['https://proxy.test:1080'],
            'unknown scheme' => ['ftp://proxy.test:1080'],
            'missing host' => ['socks5h://:1080'],
            'missing port' => ['socks5h://proxy.test'],
            'zero port' => ['socks5h://proxy.test:0'],
            'out of range port' => ['socks5h://proxy.test:65536'],
            'credentials' => ['socks5h://user:secret@proxy.test:1080'],
            'path' => ['socks5h://proxy.test:1080/path'],
            'query' => ['socks5h://proxy.test:1080?secret=value'],
            'fragment' => ['socks5h://proxy.test:1080#fragment'],
            'control character' => ["socks5h://proxy.test:1080\n"],
        ];
    }

    #[DataProvider('invalidProxyProvider')]
    public function test_it_rejects_invalid_or_unsafe_proxy_uris(string $uri): void
    {
        try {
            Socks5Proxy::fromUri($uri);
            self::fail('Expected proxy configuration failure.');
        } catch (ConfigurationException $exception) {
            if ($uri !== '') {
                self::assertStringNotContainsString($uri, $exception->getMessage());
            }
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    public function test_connection_configuration_allows_the_generic_direct_transport_when_proxy_is_absent(): void
    {
        $config = config('tmetric.connections.default');
        unset($config['proxy']);

        $connection = ConnectionConfig::fromArray('default', $config);

        self::assertNull($connection->proxy());
    }

    public function test_proxy_and_connection_debug_output_are_redacted(): void
    {
        $proxy = Socks5Proxy::fromUri('socks5h://proxy-secret.test:1080');
        $connection = ConnectionConfig::fromArray('default', [
            ...config('tmetric.connections.default'),
            'proxy' => $proxy->uri(),
        ]);

        foreach ([$proxy, $connection] as $value) {
            $debug = print_r($value, true);

            self::assertStringContainsString('[REDACTED]', $debug);
            self::assertStringNotContainsString('proxy-secret.test', $debug);
            self::assertStringNotContainsString('synthetic-secret-token', $debug);
        }
    }

    public function test_proxy_and_connection_cannot_be_serialized(): void
    {
        $proxy = Socks5Proxy::fromUri('socks5h://proxy-secret.test:1080');

        foreach ([
            $proxy,
            ConnectionConfig::fromArray('default', config('tmetric.connections.default')),
        ] as $value) {
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
