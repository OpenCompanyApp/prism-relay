<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay;

use Illuminate\Support\ServiceProvider;
use OpenCompany\PrismRelay\Contracts\RelayListener;
use OpenCompany\PrismRelay\Registry\RelayRegistry;
use OpenCompany\PrismRelay\Registry\RelayRegistryBuilder;
use OpenCompany\PrismRelay\Support\NullRelayListener;
use Prism\Prism\PrismManager;

class PrismRelayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RelayRegistryBuilder::class);
        $this->app->singleton(RelayRegistry::class, fn ($app) => $app->make(RelayRegistryBuilder::class)->build());
        $this->app->singleton(RelayManager::class, fn ($app) => new RelayManager($app->make(RelayRegistry::class)));

        $this->app->singleton(Relay::class, function ($app) {
            $listener = $app->bound(RelayListener::class)
                ? $app->make(RelayListener::class)
                : new NullRelayListener;

            return new Relay($listener);
        });
    }

    public function boot(): void
    {
        $this->app->afterResolving(PrismManager::class, function (PrismManager $manager) {
            $this->app->make(RelayManager::class)->register($manager);
        });
    }
}
