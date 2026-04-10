<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Capabilities;

use OpenCompany\PrismRelay\Registry\RelayRegistry;
use OpenCompany\PrismRelay\Registry\RelayRegistryBuilder;

/**
 * Declares which request parameters a specific provider supports.
 *
 * Create via ProviderCapabilities::for('z') to get capabilities for
 * the z.ai (GLM Coding) provider, then call supportsTemperature(),
 * supportsTopP(), etc. to check before sending parameters.
 *
 * Used by KosmoKrator (and other consumers) to strip unsupported
 * parameters before sending requests — avoids 400 errors from
 * strict APIs like z.ai (GLM Coding) or Codex.
 */
class ProviderCapabilities
{
    private string $provider;

    private RelayRegistry $registry;

    public function __construct(string $provider, ?RelayRegistry $registry = null)
    {
        $this->provider = $provider;
        $this->registry = $registry ?? (new RelayRegistryBuilder)->build();
    }

    /**
     * Create a capabilities instance for a specific provider.
     */
    public static function for(string $provider): self
    {
        return new self($provider);
    }

    /**
     * Whether the provider supports the `temperature` parameter.
     */
    public function supportsTemperature(): bool
    {
        return $this->capability('temperature');
    }

    /**
     * Whether the provider supports the `top_p` parameter.
     */
    public function supportsTopP(): bool
    {
        return $this->capability('top_p');
    }

    /**
     * Whether the provider supports the `max_tokens` / `max_output_tokens` parameter.
     */
    public function supportsMaxTokens(): bool
    {
        return $this->capability('max_tokens');
    }

    /**
     * Whether the provider supports streaming responses.
     */
    public function supportsStreaming(): bool
    {
        return $this->capability('streaming');
    }

    /**
     * Whether the provider supports `stream_options.include_usage` for token
     * usage data in streaming SSE events.
     *
     * Ollama hangs when this option is sent. Providers that don't support it
     * will still work — usage data just won't be available in stream events.
     */
    public function supportsStreamUsage(): bool
    {
        return $this->capability('stream_usage');
    }

    private function capability(string $key): bool
    {
        return $this->registry->capabilities($this->provider)[$key] ?? true;
    }

    /**
     * Default capability map for all known providers.
     *
     * Providers not listed here default to all-true (permissive).
     *
     * @return array<string, array{temperature: bool, top_p: bool, max_tokens: bool, streaming: bool, stream_usage: bool}>
     */
    public static function defaults(): array
    {
        return [
            // z.ai (GLM Coding) — rejects temperature, top_p; no streaming via Prism
            'z' => [
                'temperature' => false,
                'top_p' => false,
                'max_tokens' => true,
                'streaming' => false,
                'stream_usage' => false,
            ],
            // z-api (GLM) — same backend constraints
            'z-api' => [
                'temperature' => false,
                'top_p' => false,
                'max_tokens' => true,
                'streaming' => true,
                'stream_usage' => true,
            ],
            // Codex (ChatGPT subscription) — rejects temperature, top_p on /responses endpoint
            'codex' => [
                'temperature' => false,
                'top_p' => false,
                'max_tokens' => true,
                'streaming' => true,
                'stream_usage' => true,
            ],
            // Kimi Coding — no temperature control
            'kimi-coding' => [
                'temperature' => false,
                'top_p' => false,
                'max_tokens' => true,
                'streaming' => true,
                'stream_usage' => true,
            ],
            // Ollama — local models, no stream_options support (causes hangs)
            'ollama' => [
                'temperature' => true,
                'top_p' => true,
                'max_tokens' => true,
                'streaming' => true,
                'stream_usage' => false,
            ],
        ];
    }
}
