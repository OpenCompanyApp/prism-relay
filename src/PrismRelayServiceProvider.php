<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay;

use Illuminate\Support\ServiceProvider;
use OpenCompany\PrismRelay\Contracts\RelayListener;
use OpenCompany\PrismRelay\Support\NullRelayListener;
use Prism\Prism\PrismManager;

class PrismRelayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RelayManager::class);

        $this->app->singleton(Relay::class, function ($app) {
            $listener = $app->bound(RelayListener::class)
                ? $app->make(RelayListener::class)
                : new NullRelayListener;

            return new Relay($listener);
        });
    }

    public function boot(): void
    {
        $this->afterResolving(PrismManager::class, function (PrismManager $manager) {
            $this->app->make(RelayManager::class)->register($manager);
        });
    }
}
