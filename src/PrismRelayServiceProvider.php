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
        $this->app->singleton(RelayRegistry::class, function ($app) {
            $builder = $app->make(RelayRegistryBuilder::class);
            $enabled = (bool) data_get($app->make('config')->all(), 'prism-relay.models_dev.enabled', true);

            return $enabled ? $builder->build() : $builder->buildBundled();
        });
        $this->app->singleton(RelayManager::class, fn ($app) => new RelayManager($app->make(RelayRegistry::class)));

        $this->app->singleton(Relay::class, function ($app) {
            $listener = $app->bound(RelayListener::class)
                ? $app->make(RelayListener::class)
                : new NullRelayListener;

            return new Relay(
                listener: $listener,
                providerMeta: new \OpenCompany\PrismRelay\Meta\ProviderMeta($app->make(RelayRegistry::class)),
            );
        });
    }

    public function boot(): void
    {
        $this->app->afterResolving(PrismManager::class, function (PrismManager $manager) {
            $this->app->make(RelayManager::class)->register($manager);
        });
    }
}
