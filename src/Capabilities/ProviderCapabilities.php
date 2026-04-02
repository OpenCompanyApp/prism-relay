<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Capabilities;

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

    /** @var array<string, array{temperature: bool, top_p: bool, max_tokens: bool, streaming: bool}> */
    private array $capabilities;

    /**
     * @param  array<string, array{temperature: bool, top_p: bool, max_tokens: bool, streaming: bool}>  $capabilities
     */
    public function __construct(string $provider, ?array $capabilities = null)
    {
        $this->provider = $provider;
        $this->capabilities = $capabilities ?? self::defaults();
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
        return $this->capabilities[$this->provider]['temperature'] ?? true;
    }

    /**
     * Whether the provider supports the `top_p` parameter.
     */
    public function supportsTopP(): bool
    {
        return $this->capabilities[$this->provider]['top_p'] ?? true;
    }

    /**
     * Whether the provider supports the `max_tokens` / `max_output_tokens` parameter.
     */
    public function supportsMaxTokens(): bool
    {
        return $this->capabilities[$this->provider]['max_tokens'] ?? true;
    }

    /**
     * Whether the provider supports streaming responses.
     */
    public function supportsStreaming(): bool
    {
        return $this->capabilities[$this->provider]['streaming'] ?? true;
    }

    /**
     * Default capability map for all known providers.
     *
     * Providers not listed here default to all-true (permissive).
     *
     * @return array<string, array{temperature: bool, top_p: bool, max_tokens: bool, streaming: bool}>
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
            ],
            // z-api (GLM) — same backend constraints
            'z-api' => [
                'temperature' => false,
                'top_p' => false,
                'max_tokens' => true,
                'streaming' => true,
            ],
            // Codex (ChatGPT subscription) — rejects temperature, top_p on /responses endpoint
            'codex' => [
                'temperature' => false,
                'top_p' => false,
                'max_tokens' => true,
                'streaming' => true,
            ],
            // Kimi Coding — no temperature control
            'kimi-coding' => [
                'temperature' => false,
                'top_p' => false,
                'max_tokens' => true,
                'streaming' => true,
            ],
        ];
    }
}
