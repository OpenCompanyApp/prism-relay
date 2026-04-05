<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Reasoning;

/**
 * Provider-specific reasoning/thinking strategy.
 *
 * Determines how each provider handles extended reasoning: whether it accepts
 * an effort parameter, always reasons, or doesn't support it. Also handles
 * building request params and extracting reasoning text from responses.
 *
 * Follows the same pattern as CacheStrategy for prompt caching.
 */
class ReasoningStrategy
{
    /**
     * Determine what reasoning capability a provider has.
     *
     * This is the provider-level API format — whether reasoning is controlled
     * via a parameter, always-on, or unsupported. Use ProviderMeta::supportsThinking()
     * to check whether a specific model supports reasoning.
     */
    public static function capability(string $provider): ReasoningCapability
    {
        return match ($provider) {
            // Accept reasoning_effort parameter
            'openai', 'azure', 'azure-cognitive-services' => ReasoningCapability::Effort,
            'xai' => ReasoningCapability::Effort,

            // Thinking models always reason — no explicit param
            'deepseek' => ReasoningCapability::AlwaysOn,
            'stepfun', 'stepfun-plan' => ReasoningCapability::AlwaysOn,
            'kimi', 'kimi-coding', 'kimi-for-coding' => ReasoningCapability::AlwaysOn,
            'groq' => ReasoningCapability::AlwaysOn,
            'fireworks-ai' => ReasoningCapability::AlwaysOn,
            'togetherai' => ReasoningCapability::AlwaysOn,
            'cerebras' => ReasoningCapability::AlwaysOn,
            'mistral' => ReasoningCapability::AlwaysOn,
            'perplexity', 'perplexity-agent' => ReasoningCapability::AlwaysOn,
            'openrouter' => ReasoningCapability::AlwaysOn,
            'z', 'z-api' => ReasoningCapability::AlwaysOn,
            'minimax', 'minimax-cn' => ReasoningCapability::AlwaysOn,
            'mimo', 'mimo-api' => ReasoningCapability::AlwaysOn,

            default => ReasoningCapability::None,
        };
    }

    /**
     * Build provider-specific request params for a given reasoning effort level.
     *
     * Only returns params for Effort-type providers. AlwaysOn and None return empty.
     *
     * @param  string  $provider  Provider identifier
     * @param  string  $effort  Reasoning effort level: 'low', 'medium', 'high'
     * @return array<string, mixed> Request payload additions
     */
    public static function requestParams(string $provider, string $effort): array
    {
        if (self::capability($provider) !== ReasoningCapability::Effort) {
            return [];
        }

        return match ($provider) {
            'openai', 'azure', 'azure-cognitive-services' => ['reasoning_effort' => $effort],
            'xai' => ['reasoning_effort' => $effort],
            default => [],
        };
    }

    /**
     * Extract reasoning/thinking text from a provider's response message data.
     *
     * Different providers return reasoning content in different fields.
     * This normalizes the extraction so consumers don't need provider-specific logic.
     *
     * @param  string  $provider  Provider identifier
     * @param  array<string, mixed>  $message  The 'message' object from choices[0]
     * @return string Extracted reasoning text, or empty string if none
     */
    public static function extractReasoning(string $provider, array $message): string
    {
        // Provider-specific primary field, with universal fallback
        return match ($provider) {
            // StepFun uses 'reasoning' as primary field
            'stepfun', 'stepfun-plan' => (string) ($message['reasoning'] ?? $message['reasoning_content'] ?? ''),

            // Most providers use 'reasoning_content' (DeepSeek, XAI, OpenAI, etc.)
            default => (string) ($message['reasoning_content'] ?? $message['reasoning'] ?? ''),
        };
    }
}
