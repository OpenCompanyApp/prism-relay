<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Meta;

use OpenCompany\PrismRelay\Caching\CacheCapability;
use OpenCompany\PrismRelay\Caching\CacheStrategy;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class ProviderMeta
{
    private RelayRegistry $registry;

    /**
     * @param  array<string, array<string, mixed>>|null  $providers
     */
    public function __construct(?array $providers = null)
    {
        $this->registry = new RelayRegistry($providers);
    }

    /**
     * Get the default model for a provider.
     */
    public function defaultModel(string $provider): ?string
    {
        return $this->registry->provider($provider)['default_model'] ?? null;
    }

    /**
     * Get the default API URL for a provider.
     */
    public function url(string $provider): ?string
    {
        return $this->registry->provider($provider)['url'] ?? null;
    }

    /**
     * Get the context window size for a specific model.
     */
    public function contextWindow(string $provider, string $model): int
    {
        $info = $this->registry->model($provider, $model);

        return isset($info['context']) ? (int) $info['context'] : 32000;
    }

    /**
     * Get detailed model info including pricing and cache capability.
     */
    public function modelInfo(string $provider, string $model): ModelInfo
    {
        $info = $this->findModelInfo($provider, $model);

        return new ModelInfo(
            model: $model,
            contextWindow: (int) ($info['context'] ?? $this->contextWindow($provider, $model)),
            maxOutput: (int) ($info['max_output'] ?? 4096),
            inputPricePerMillion: isset($info['input']) ? (float) $info['input'] : null,
            outputPricePerMillion: isset($info['output']) ? (float) $info['output'] : null,
            cachedInputPricePerMillion: isset($info['cached_input']) ? (float) $info['cached_input'] : null,
            cachedWritePricePerMillion: isset($info['cached_write']) ? (float) $info['cached_write'] : null,
            thinking: (bool) ($info['thinking'] ?? false),
            displayName: $info['display_name'] ?? null,
            cacheCapability: CacheStrategy::capability($provider),
        );
    }

    /**
     * Get the max output tokens for a model.
     */
    public function maxOutput(string $provider, string $model): int
    {
        $info = $this->findModelInfo($provider, $model);

        return (int) ($info['max_output'] ?? 4096);
    }

    /**
     * Check if a model supports extended thinking/reasoning.
     */
    public function supportsThinking(string $provider, string $model): bool
    {
        $info = $this->findModelInfo($provider, $model);

        return (bool) ($info['thinking'] ?? false);
    }

    /**
     * Get the display name for a model.
     */
    public function displayName(string $provider, string $model): ?string
    {
        $info = $this->findModelInfo($provider, $model);

        return $info['display_name'] ?? null;
    }

    /**
     * Find model info with exact match, then prefix match fallback.
     *
     * @return array<string, mixed>
     */
    private function findModelInfo(string $provider, string $model): array
    {
        return $this->registry->model($provider, $model);
    }

    /**
     * Get all known provider names.
     *
     * @return string[]
     */
    public function allProviders(): array
    {
        return $this->registry->canonicalProviders();
    }

    /**
     * Check if a provider is known to the metadata registry.
     */
    public function has(string $provider): bool
    {
        return $this->registry->hasProvider($provider);
    }

    /**
     * Get all known models for a provider.
     *
     * @return string[]
     */
    public function models(string $provider): array
    {
        return $this->registry->models($provider);
    }
}
