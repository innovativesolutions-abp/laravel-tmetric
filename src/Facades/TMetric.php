<?php

namespace InnovativeSolutions\TMetric\Facades;

use Illuminate\Support\Facades\Facade;
use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Testing\FakeTransport;
use InnovativeSolutions\TMetric\TMetricManager;

/**
 * @method static \InnovativeSolutions\TMetric\Connection connection(?string $name = null)
 * @method static \InnovativeSolutions\TMetric\Connection connect(array $config, string $name = 'runtime')
 */
final class TMetric extends Facade
{
    private static ?FakeTransport $fake = null;

    /** @param array<int, mixed> $responses */
    public static function fake(array $responses = []): FakeTransport
    {
        $fake = new FakeTransport($responses);
        self::$fake = $fake;

        app()->instance(Transport::class, $fake);
        app()->forgetInstance(TMetricManager::class);
        self::clearResolvedInstance('tmetric');

        return $fake;
    }

    public static function assertRequested(callable $predicate): void
    {
        if (self::$fake === null) {
            throw new \LogicException('TMetric::fake() must be called before request assertions.');
        }

        self::$fake->assertRequested($predicate);
    }

    protected static function getFacadeAccessor(): string
    {
        return 'tmetric';
    }
}
