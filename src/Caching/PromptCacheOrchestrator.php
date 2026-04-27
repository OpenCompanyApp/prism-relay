<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Caching;

use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Gemini\Gemini;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

final class PromptCacheOrchestrator
{
    public function __construct(
        private readonly ?PrismManager $prismManager = null,
        private readonly ?GeminiCacheStore $geminiCacheStore = null,
        private readonly int $geminiCacheTtlSeconds = 300,
    ) {}

    /**
     * @param  SystemMessage[]  $systemPrompts
     * @param  array<int, \Prism\Prism\Contracts\Message>  $messages
     * @param  array<int, mixed>  $tools
     */
    public function plan(string $provider, string $model, array $systemPrompts, array $messages, array $tools = []): PromptCachePlan
    {
        $basePlan = PromptCachePlanner::plan($provider, $systemPrompts, $messages, tools: $tools);

        if ($provider !== 'gemini') {
            return $basePlan;
        }

        return $this->planGemini($model, $basePlan);
    }

    private function planGemini(string $model, PromptCachePlan $basePlan): PromptCachePlan
    {
        if ($basePlan->systemPrompts === []) {
            return $basePlan;
        }

        $cacheableSystemPrompts = [$basePlan->systemPrompts[0]];
        $remainingSystemPrompts = array_slice($basePlan->systemPrompts, 1);
        $cachedContentName = $this->resolveGeminiCachedContentName($model, $cacheableSystemPrompts);

        if ($cachedContentName === null) {
            return $basePlan;
        }

        return new PromptCachePlan(
            systemPrompts: $remainingSystemPrompts,
            messages: $basePlan->messages,
            providerOptions: ['cachedContentName' => $cachedContentName],
            tools: $basePlan->tools,
        );
    }

    /**
     * @param  SystemMessage[]  $systemPrompts
     */
    private function resolveGeminiCachedContentName(string $model, array $systemPrompts): ?string
    {
        if ($this->prismManager === null) {
            return null;
        }

        $cacheKey = $this->buildGeminiCacheKey($model, $systemPrompts);

        if ($this->geminiCacheStore !== null) {
            $cachedName = $this->geminiCacheStore->get($cacheKey);
            if ($cachedName !== null) {
                return $cachedName;
            }
        }

        try {
            $provider = $this->prismManager->resolve('gemini');
        } catch (\Throwable) {
            return null;
        }

        if (! $provider instanceof Gemini) {
            return null;
        }

        try {
            $cachedObject = $provider->cache(
                model: $model,
                messages: [],
                systemPrompts: $systemPrompts,
                ttl: $this->geminiCacheTtlSeconds,
            );
        } catch (\Throwable) {
            return null;
        }

        $this->geminiCacheStore?->put($cacheKey, $cachedObject->name, $cachedObject->expiresAt);

        return $cachedObject->name !== '' ? $cachedObject->name : null;
    }

    /**
     * @param  SystemMessage[]  $systemPrompts
     */
    private function buildGeminiCacheKey(string $model, array $systemPrompts): string
    {
        $payload = [
            'model' => $model,
            'system_prompts' => array_map(
                fn (SystemMessage $prompt): array => [
                    'content' => $prompt->content,
                    'provider_options' => $prompt->providerOptions(),
                ],
                $systemPrompts,
            ),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
