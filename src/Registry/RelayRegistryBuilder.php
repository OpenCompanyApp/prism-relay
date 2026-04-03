<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Registry;

use OpenCompany\PrismRelay\ModelsDev\ModelsDevClient;

final class RelayRegistryBuilder
{
    public function __construct(
        private readonly ?ModelsDevClient $modelsDev = null,
        private readonly ?string $configDir = null,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $appOverrides
     */
    public function build(array $appOverrides = [], bool $includeLiveProviders = true): RelayRegistry
    {
        return new RelayRegistry($this->providers($appOverrides, $includeLiveProviders));
    }

    /**
     * @param  array<string, array<string, mixed>>  $appOverrides
     */
    public function buildBundled(array $appOverrides = []): RelayRegistry
    {
        return $this->build($appOverrides, false);
    }

    /**
     * @param  array<string, array<string, mixed>>  $appOverrides
     * @return array<string, array<string, mixed>>
     */
    public function providers(array $appOverrides = [], bool $includeLiveProviders = true): array
    {
        $bundled = $this->loadBundledSnapshot();
        $manual = $this->loadBundledManual();
        $live = $includeLiveProviders
            ? ($this->modelsDev ?? new ModelsDevClient)->providers()
            : [];

        $providers = $this->merge($bundled, $live);
        $providers = $this->applyImports($providers, $manual);
        $providers = $this->merge($providers, $manual);
        $providers = $this->applyImports($providers, $appOverrides);
        $providers = $this->merge($providers, $appOverrides);

        foreach ($providers as $providerId => $provider) {
            $providers[$providerId]['source'] = isset($appOverrides[$providerId]) ? 'custom' : ((string) ($provider['source'] ?? 'built_in'));
        }

        return $providers;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadBundledSnapshot(): array
    {
        $configDir = $this->configDir();
        $generated = $configDir.'/relay.generated.php';
        $snapshot = $configDir.'/relay.php';

        if (is_file($generated)) {
            /** @var array<string, array<string, mixed>> $providers */
            $providers = require $generated;

            return $providers;
        }

        if (is_file($snapshot)) {
            /** @var array<string, array<string, mixed>> $providers */
            $providers = require $snapshot;

            return $providers;
        }

        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadBundledManual(): array
    {
        $manual = $this->configDir().'/relay.manual.php';
        if (! is_file($manual)) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $providers */
        $providers = require $manual;

        return $providers;
    }

    /**
     * @param  array<string, array<string, mixed>>  $providers
     * @param  array<string, array<string, mixed>>  $overrides
     * @return array<string, array<string, mixed>>
     */
    private function applyImports(array $providers, array $overrides): array
    {
        foreach ($overrides as $providerId => $override) {
            if (! is_array($override)) {
                continue;
            }

            $sourceProvider = strtolower((string) ($override['models_dev_provider'] ?? ''));
            if ($sourceProvider === '' || ! isset($providers[$sourceProvider])) {
                continue;
            }

            $imported = $providers[$sourceProvider];
            unset($imported['source']);

            $providers[$providerId] = $this->merge($providers[$providerId] ?? [], $imported);
        }

        return $providers;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (is_int($key)) {
                if (! in_array($value, $base, true)) {
                    $base[] = $value;
                }

                continue;
            }

            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = $this->merge($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private function configDir(): string
    {
        return $this->configDir ?? dirname(__DIR__, 2).'/config';
    }
}
