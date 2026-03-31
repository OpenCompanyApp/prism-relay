<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Meta;

use OpenCompany\PrismRelay\Caching\CacheCapability;
use OpenCompany\PrismRelay\Caching\CacheStrategy;

class ProviderMeta
{
    /** @var array<string, array<string, mixed>> */
    private array $providers;

    /**
     * @param  array<string, array<string, mixed>>|null  $providers
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? require __DIR__ . '/../../config/relay.php';
    }

    /**
     * Get the default model for a provider.
     */
    public function defaultModel(string $provider): ?string
    {
        return $this->providers[$provider]['default_model'] ?? null;
    }

    /**
     * Get the default API URL for a provider.
     */
    public function url(string $provider): ?string
    {
        return $this->providers[$provider]['url'] ?? null;
    }

    /**
     * Get the context window size for a specific model.
     */
    public function contextWindow(string $provider, string $model): int
    {
        $models = $this->providers[$provider]['models'] ?? [];

        if (isset($models[$model]['context'])) {
            return (int) $models[$model]['context'];
        }

        // Try prefix matching (e.g., 'claude-sonnet' matches 'claude-sonnet-4-5-*')
        foreach ($models as $modelKey => $info) {
            if (str_starts_with($model, $modelKey) || str_starts_with($modelKey, $model)) {
                return (int) ($info['context'] ?? 32000);
            }
        }

        return 32000; // Conservative default
    }

    /**
     * Get detailed model info including pricing and cache capability.
     */
    public function modelInfo(string $provider, string $model): ModelInfo
    {
        $models = $this->providers[$provider]['models'] ?? [];
        $info = $models[$model] ?? [];

        return new ModelInfo(
            model: $model,
            contextWindow: (int) ($info['context'] ?? $this->contextWindow($provider, $model)),
            inputPricePerMillion: isset($info['input']) ? (float) $info['input'] : null,
            outputPricePerMillion: isset($info['output']) ? (float) $info['output'] : null,
            cachedInputPricePerMillion: isset($info['cached_input']) ? (float) $info['cached_input'] : null,
            cacheCapability: CacheStrategy::capability($provider),
        );
    }

    /**
     * Get all known provider names.
     *
     * @return string[]
     */
    public function allProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if a provider is known to the metadata registry.
     */
    public function has(string $provider): bool
    {
        return isset($this->providers[$provider]);
    }

    /**
     * Get all known models for a provider.
     *
     * @return string[]
     */
    public function models(string $provider): array
    {
        return array_keys($this->providers[$provider]['models'] ?? []);
    }
}
