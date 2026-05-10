<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\ModelsDev;

final class ModelsDevClient
{
    private const ENDPOINT = 'https://models.dev/api.json';

    /** @var array<string, string> */
    private const NPM_DRIVER_MAP = [
        '@ai-sdk/openai' => 'openai',
        '@ai-sdk/openai-compatible' => 'openai-compatible',
        '@ai-sdk/openai-compatible-v2' => 'openai-compatible',
        '@ai-sdk/anthropic' => 'anthropic',
        '@ai-sdk/google' => 'gemini',
        '@ai-sdk/google-vertex' => 'google-vertex',
        '@ai-sdk/groq' => 'groq',
        '@ai-sdk/mistral' => 'mistral',
        '@ai-sdk/xai' => 'xai',
        '@ai-sdk/ollama' => 'ollama',
        '@ai-sdk/perplexity' => 'perplexity',
        '@openrouter/ai-sdk-provider' => 'openrouter',
        '@ai-sdk/gateway' => 'openai-compatible',
        '@ai-sdk/azure' => 'openai-compatible',
        '@ai-sdk/amazon-bedrock' => 'amazon-bedrock',
    ];

    public function __construct(
        private readonly ?string $cachePath = null,
        private readonly int $ttlSeconds = 3600,
        private readonly string $endpoint = self::ENDPOINT,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providers(): array
    {
        $raw = $this->loadRawPayload();
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $providerId => $provider) {
            if (! is_array($provider)) {
                continue;
            }

            $normalized[strtolower((string) $providerId)] = $this->normalizeProvider(
                strtolower((string) $providerId),
                $provider,
                $raw,
            );
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadRawPayload(): ?array
    {
        $cached = $this->readCache();
        if ($cached !== null && $this->isFresh($cached)) {
            return is_array($cached['data'] ?? null) ? $cached['data'] : null;
        }

        $fetched = $this->fetchRemote();
        if ($fetched !== null) {
            $this->writeCache($fetched);

            return $fetched;
        }

        return is_array($cached['data'] ?? null) ? $cached['data'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRemote(): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'User-Agent: prism-relay/1.0 (+https://github.com/OpenCompanyApp/prism-relay)',
                ]),
            ],
        ]);

        $json = @file_get_contents($this->endpoint, false, $context);
        if (! is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{fetched_at:int,data:array<string,mixed>}|null
     */
    private function readCache(): ?array
    {
        $path = $this->cacheFile();
        if (! is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! isset($payload['fetched_at']) || ! isset($payload['data'])) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeCache(array $data): void
    {
        $path = $this->cacheFile();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        @file_put_contents($path, (string) json_encode([
            'fetched_at' => time(),
            'data' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array{fetched_at:int,data:array<string,mixed>}  $cache
     */
    private function isFresh(array $cache): bool
    {
        return ($cache['fetched_at'] + $this->ttlSeconds) >= time();
    }

    private function cacheFile(): string
    {
        if ($this->cachePath !== null && $this->cachePath !== '') {
            return $this->cachePath;
        }

        $base = getenv('XDG_CACHE_HOME');
        if (! is_string($base) || $base === '') {
            $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();
            $base = rtrim($home, '/').'/'.'.cache';
        }

        return rtrim($base, '/').'/prism-relay/models-dev.json';
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $allProviders
     * @return array<string, mixed>
     */
    private function normalizeProvider(string $providerId, array $provider, array $allProviders): array
    {
        $models = [];

        foreach ($provider['models'] ?? [] as $modelId => $model) {
            if (! is_array($model)) {
                continue;
            }

            $models[(string) $modelId] = $this->normalizeModel($providerId, (string) $modelId, $model, $allProviders);
        }

        return [
            'aliases' => array_values(array_map('strval', is_array($provider['aliases'] ?? null) ? $provider['aliases'] : [])),
            'models' => $models,
            'driver' => $this->inferDriver($providerId, $provider),
            'label' => (string) ($provider['name'] ?? $this->humanize($providerId)),
            'url' => (string) ($provider['api'] ?? ''),
            'npm' => $provider['npm'] ?? null,
            'doc' => $provider['doc'] ?? null,
            'env' => array_values(array_map('strval', is_array($provider['env'] ?? null) ? $provider['env'] : [])),
            'capabilities' => null,
            'default_model' => $this->defaultModelId($models),
            'modalities' => $this->aggregateProviderModalities($models),
            'source' => 'built_in',
        ];
    }

    /**
     * @param  array<string, mixed>  $model
     * @param  array<string, mixed>  $allProviders
     * @return array<string, mixed>
     */
    private function normalizeModel(string $providerId, string $modelId, array $model, array $allProviders): array
    {
        $cost = is_array($model['cost'] ?? null) ? $model['cost'] : [];
        $limit = is_array($model['limit'] ?? null) ? $model['limit'] : [];
        $modalities = is_array($model['modalities'] ?? null) ? $model['modalities'] : [];
        $reference = $this->referencePricing($providerId, $modelId, $allProviders);
        $isCodingPlan = $this->isCodingPlanProvider($providerId);
        $isTokenPlan = $this->isTokenPlanProvider($providerId);
        $isFree = ! $isCodingPlan && ! $isTokenPlan
            && ((float) ($cost['input'] ?? 0.0) === 0.0)
            && ((float) ($cost['output'] ?? 0.0) === 0.0);

        return [
            'display_name' => (string) ($model['name'] ?? $modelId),
            'context' => (int) ($limit['context'] ?? 32000),
            'max_output' => (int) ($limit['output'] ?? 4096),
            'input' => isset($cost['input']) ? (float) $cost['input'] : null,
            'output' => isset($cost['output']) ? (float) $cost['output'] : null,
            'cached_input' => isset($cost['cache_read']) ? (float) $cost['cache_read'] : null,
            'cached_write' => isset($cost['cache_write']) ? (float) $cost['cache_write'] : null,
            'thinking' => (bool) ($model['reasoning'] ?? false),
            'tool_call' => (bool) ($model['tool_call'] ?? false),
            'attachments' => (bool) ($model['attachment'] ?? false),
            'modalities' => [
                'input' => $this->stringList($modalities['input'] ?? ['text']),
                'output' => $this->stringList($modalities['output'] ?? ['text']),
            ],
            'status' => is_string($model['status'] ?? null) ? $model['status'] : null,
            'pricing_kind' => $isCodingPlan ? 'coding_plan' : ($isTokenPlan ? 'token_plan' : ($isFree ? 'public_free' : 'paid')),
            'reference_input' => $reference['input'],
            'reference_output' => $reference['output'],
        ];
    }

    /**
     * @param  array<string, mixed>  $allProviders
     * @return array{input:?float,output:?float}
     */
    private function referencePricing(string $providerId, string $modelId, array $allProviders): array
    {
        $referenceProvider = $this->referenceProviderId($providerId);
        if ($referenceProvider === null || ! isset($allProviders[$referenceProvider]['models'])) {
            return ['input' => null, 'output' => null];
        }

        $models = is_array($allProviders[$referenceProvider]['models'] ?? null)
            ? $allProviders[$referenceProvider]['models']
            : [];

        $reference = $models[$modelId] ?? $this->lookupCaseInsensitive($models, $modelId);
        if (! is_array($reference)) {
            return ['input' => null, 'output' => null];
        }

        $cost = is_array($reference['cost'] ?? null) ? $reference['cost'] : [];

        return [
            'input' => isset($cost['input']) ? (float) $cost['input'] : null,
            'output' => isset($cost['output']) ? (float) $cost['output'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $models
     * @return array<string, mixed>|null
     */
    private function lookupCaseInsensitive(array $models, string $modelId): ?array
    {
        $needle = strtolower($modelId);

        foreach ($models as $candidateId => $candidate) {
            if (strtolower((string) $candidateId) === $needle && is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function referenceProviderId(string $providerId): ?string
    {
        if (str_ends_with($providerId, '-coding-plan-cn')) {
            return substr($providerId, 0, -strlen('-coding-plan-cn')).'-cn';
        }

        if (str_ends_with($providerId, '-cn-coding-plan')) {
            return substr($providerId, 0, -strlen('-cn-coding-plan')).'-cn';
        }

        if (str_ends_with($providerId, '-coding-plan')) {
            return substr($providerId, 0, -strlen('-coding-plan'));
        }

        return null;
    }

    private function isCodingPlanProvider(string $providerId): bool
    {
        return str_ends_with($providerId, '-coding-plan')
            || str_ends_with($providerId, '-coding-plan-cn')
            || str_ends_with($providerId, '-cn-coding-plan');
    }

    private function isTokenPlanProvider(string $providerId): bool
    {
        return str_contains($providerId, 'token-plan');
    }

    /**
     * @param  array<string, array<string, mixed>>  $models
     * @return array{input:list<string>,output:list<string>}
     */
    private function aggregateProviderModalities(array $models): array
    {
        $input = [];
        $output = [];

        foreach ($models as $model) {
            foreach ($this->stringList($model['modalities']['input'] ?? ['text']) as $item) {
                if (! in_array($item, $input, true)) {
                    $input[] = $item;
                }
            }

            foreach ($this->stringList($model['modalities']['output'] ?? ['text']) as $item) {
                if (! in_array($item, $output, true)) {
                    $output[] = $item;
                }
            }
        }

        return [
            'input' => $input === [] ? ['text'] : $input,
            'output' => $output === [] ? ['text'] : $output,
        ];
    }

    private function defaultModelId(array $models): string
    {
        $modelId = array_key_first($models);

        return is_string($modelId) ? $modelId : '';
    }

    /**
     * @param  array<string, mixed>  $provider
     */
    private function inferDriver(string $providerId, array $provider): string
    {
        $driver = match ($providerId) {
            'z', 'zai-coding-plan', 'kuae-cloud-coding-plan' => 'glm-coding',
            'z-api', 'zai' => 'glm',
            'kimi' => 'kimi',
            'kimi-coding', 'moonshot' => 'kimi-coding',
            'minimax' => 'minimax',
            'minimax-cn' => 'minimax-cn',
            default => null,
        };

        if ($driver !== null) {
            return $driver;
        }

        $npm = strtolower((string) ($provider['npm'] ?? ''));
        if ($npm !== '' && isset(self::NPM_DRIVER_MAP[$npm])) {
            return self::NPM_DRIVER_MAP[$npm];
        }

        return 'openai-compatible';
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return ['text'];
        }

        return array_values(array_map('strval', array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== '')));
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }
}
