<?php

namespace InnovativeSolutions\TMetric;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use InnovativeSolutions\TMetric\Contracts\Sleeper;
use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Http\LaravelHttpTransport;
use InnovativeSolutions\TMetric\Http\NativeSleeper;

final class TMetricServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tmetric.php', 'tmetric');

        $this->app->singleton(Sleeper::class, NativeSleeper::class);
        $this->app->singleton(
            Transport::class,
            fn ($app): LaravelHttpTransport => new LaravelHttpTransport(
                $app->make(Factory::class),
                $app->make(Sleeper::class),
            ),
        );
        $this->app->singleton(
            TMetricManager::class,
            fn ($app): TMetricManager => new TMetricManager(
                $app['config']->get('tmetric', []),
                $app->make(Transport::class),
            ),
        );
        $this->app->alias(TMetricManager::class, 'tmetric');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/tmetric.php' => config_path('tmetric.php'),
        ], 'tmetric-config');
    }
}
