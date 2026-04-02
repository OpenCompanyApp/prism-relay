<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Registry;

class RelayRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $providers;

    /** @var array<string, string> */
    private array $providerAliases = [];

    /** @var array<string, array<string, string>> */
    private array $modelAliases = [];

    /** @var array<string, array<string, string>> */
    private array $modelLowerIndex = [];

    /**
     * @param  array<string, array<string, mixed>>|null  $providers
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? require __DIR__ . '/../../config/relay.php';
        $this->providerAliases = $this->buildProviderAliases();
        $this->modelAliases = $this->buildModelAliases();
        $this->modelLowerIndex = $this->buildModelLowerIndex();
    }

    public function canonicalProvider(string $provider): ?string
    {
        $normalized = strtolower(trim($provider));

        if (isset($this->providers[$normalized])) {
            return $normalized;
        }

        return $this->providerAliases[$normalized] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function provider(string $provider): ?array
    {
        $canonical = $this->canonicalProvider($provider);

        return $canonical !== null ? $this->providers[$canonical] : null;
    }

    public function hasProvider(string $provider): bool
    {
        return $this->canonicalProvider($provider) !== null;
    }

    /**
     * @return string[]
     */
    public function canonicalProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * @return string[]
     */
    public function registrationNames(): array
    {
        $names = array_keys($this->providers);

        foreach ($this->providerAliases as $alias => $canonical) {
            $provider = $this->providers[$canonical] ?? [];

            if (($provider['register_aliases'] ?? true) === false) {
                continue;
            }

            $names[] = $alias;
        }

        return array_values(array_unique($names));
    }

    /**
     * @return string[]
     */
    public function models(string $provider): array
    {
        $provider = $this->provider($provider);

        return array_keys($provider['models'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function model(string $provider, string $model): array
    {
        $canonicalProvider = $this->canonicalProvider($provider);

        if ($canonicalProvider === null) {
            return [];
        }

        $models = $this->providers[$canonicalProvider]['models'] ?? [];
        $canonicalModel = $this->canonicalModel($canonicalProvider, $model);

        if ($canonicalModel !== null && isset($models[$canonicalModel])) {
            return $models[$canonicalModel];
        }

        foreach ($models as $modelKey => $info) {
            if (str_starts_with($model, $modelKey) || str_starts_with($modelKey, $model)) {
                return $info;
            }
        }

        return [];
    }

    public function canonicalModel(string $provider, string $model): ?string
    {
        $canonicalProvider = $this->canonicalProvider($provider);

        if ($canonicalProvider === null) {
            return null;
        }

        $models = $this->providers[$canonicalProvider]['models'] ?? [];

        if (isset($models[$model])) {
            return $model;
        }

        $lower = strtolower($model);

        if (isset($this->modelLowerIndex[$canonicalProvider][$lower])) {
            return $this->modelLowerIndex[$canonicalProvider][$lower];
        }

        if (isset($this->modelAliases[$canonicalProvider][$lower])) {
            return $this->modelAliases[$canonicalProvider][$lower];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function buildProviderAliases(): array
    {
        $aliases = [];

        foreach ($this->providers as $provider => $config) {
            $aliases[strtolower($provider)] = $provider;

            foreach ($config['aliases'] ?? [] as $alias) {
                $aliases[strtolower((string) $alias)] = $provider;
            }
        }

        return $aliases;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function buildModelAliases(): array
    {
        $aliases = [];

        foreach ($this->providers as $provider => $config) {
            $aliases[$provider] = [];

            foreach ($config['models'] ?? [] as $model => $info) {
                foreach ($info['aliases'] ?? [] as $alias) {
                    $aliases[$provider][strtolower((string) $alias)] = $model;
                }
            }
        }

        return $aliases;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function buildModelLowerIndex(): array
    {
        $index = [];

        foreach ($this->providers as $provider => $config) {
            $index[$provider] = [];

            foreach ($config['models'] ?? [] as $model => $_info) {
                $index[$provider][strtolower((string) $model)] = $model;
            }
        }

        return $index;
    }
}
