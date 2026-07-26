<?php

namespace InnovativeSolutions\TMetric\Tests\Unit;

use InnovativeSolutions\TMetric\Tests\TestCase;
use InnovativeSolutions\TMetric\TMetricManager;

final class ManagerSecurityTest extends TestCase
{
    public function test_manager_does_not_debug_raw_configuration(): void
    {
        $manager = app(TMetricManager::class);
        $debug = print_r($manager, true);

        self::assertStringContainsString('[REDACTED]', $debug);
        self::assertStringNotContainsString('synthetic-secret-token', $debug);
        self::assertStringNotContainsString('tmetric-egress.test', $debug);
    }

    public function test_manager_and_connection_cannot_be_serialized(): void
    {
        foreach ([
            app(TMetricManager::class),
            app(TMetricManager::class)->connection(),
        ] as $value) {
            try {
                serialize($value);
                self::fail('Expected serialization to be blocked.');
            } catch (\LogicException $exception) {
                self::assertStringNotContainsString('synthetic-secret-token', $exception->getMessage());
                self::assertStringNotContainsString('tmetric-egress.test', $exception->getMessage());
            }
        }
    }
}
