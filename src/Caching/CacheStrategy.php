<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Caching;

class CacheStrategy
{
    /**
     * Determine what caching capability a provider supports.
     */
    public static function capability(string $provider): CacheCapability
    {
        return match ($provider) {
            'anthropic' => CacheCapability::Ephemeral,
            'openai' => CacheCapability::Auto,
            'gemini' => CacheCapability::Dedicated,
            'openrouter' => CacheCapability::Ephemeral,
            default => CacheCapability::None,
        };
    }

    /**
     * Return provider options array to enable caching on a request.
     *
     * Apps pass this to Prism's withProviderOptions().
     * Returns empty array if provider doesn't support explicit caching.
     *
     * @return array<string, mixed>
     */
    public static function providerOptions(string $provider, ?string $cachedContentName = null): array
    {
        return match (self::capability($provider)) {
            CacheCapability::Ephemeral => [
                ...($provider === 'anthropic' ? ['cache_control' => ['type' => 'ephemeral']] : []),
            ],
            CacheCapability::Dedicated => $cachedContentName !== null
                ? ['cachedContentName' => $cachedContentName]
                : [],
            default => [],
        };
    }

    /**
     * Return per-message provider options to mark a message as cacheable.
     *
     * @return array<string, mixed>
     */
    public static function messageOptions(string $provider): array
    {
        return match (self::capability($provider)) {
            CacheCapability::Ephemeral => ['cacheType' => 'ephemeral'],
            default => [],
        };
    }

    /**
     * Whether the provider reports cache token metrics in responses.
     */
    public static function reportsCacheMetrics(string $provider): bool
    {
        return match (self::capability($provider)) {
            CacheCapability::None => false,
            default => true,
        };
    }

    /**
     * Whether the provider requires explicit opt-in for caching.
     */
    public static function requiresExplicitOptIn(string $provider): bool
    {
        return self::capability($provider) === CacheCapability::Ephemeral;
    }
}
