<?php

namespace InnovativeSolutions\TMetric\Tests;

use Illuminate\Support\Facades\Http;
use InnovativeSolutions\TMetric\TMetricServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [TMetricServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('tmetric.default', 'default');
        $app['config']->set('tmetric.connections.default', [
            'token' => 'synthetic-secret-token',
            'account_id' => '42001',
            'legacy_enabled' => true,
            'v3_base_url' => 'https://tmetric.test/api/v3',
            'legacy_base_url' => 'https://tmetric.test',
            'timeout' => 5,
            'connect_timeout' => 2,
            'max_attempts' => 3,
            'max_retry_delay_seconds' => 30,
            'proxy' => 'socks5h://tmetric-egress.test:1080',
        ]);
    }
}
