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
     * @return array<string, array<string, mixed>>
     */
    public function allProviders(): array
    {
        return $this->providers;
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

    public function source(string $provider): string
    {
        return (string) (($this->provider($provider)['source'] ?? 'built_in'));
    }

    public function driver(string $provider): string
    {
        $definition = $this->provider($provider) ?? [];

        return (string) ($definition['driver'] ?? $this->inferDriver((string) ($this->canonicalProvider($provider) ?? $provider)));
    }

    public function url(string $provider): string
    {
        return (string) (($this->provider($provider)['url'] ?? ''));
    }

    public function authMode(string $provider): string
    {
        return (string) (($this->provider($provider)['auth'] ?? ($this->canonicalProvider($provider) === 'codex' ? 'oauth' : 'api_key')));
    }

    /**
     * @return array{temperature: bool, top_p: bool, max_tokens: bool, streaming: bool}
     */
    public function capabilities(string $provider): array
    {
        $canonical = (string) ($this->canonicalProvider($provider) ?? $provider);
        $capabilities = $this->provider($provider)['capabilities'] ?? [];
        $defaults = [
            'z' => ['temperature' => false, 'top_p' => false, 'max_tokens' => true, 'streaming' => false],
            'z-api' => ['temperature' => false, 'top_p' => false, 'max_tokens' => true, 'streaming' => true],
            'codex' => ['temperature' => false, 'top_p' => false, 'max_tokens' => true, 'streaming' => true],
            'kimi-coding' => ['temperature' => false, 'top_p' => false, 'max_tokens' => true, 'streaming' => true],
        ];

        $fallback = $defaults[$canonical] ?? [];

        return [
            'temperature' => (bool) ($capabilities['temperature'] ?? $fallback['temperature'] ?? true),
            'top_p' => (bool) ($capabilities['top_p'] ?? $fallback['top_p'] ?? true),
            'max_tokens' => (bool) ($capabilities['max_tokens'] ?? $fallback['max_tokens'] ?? true),
            'streaming' => (bool) ($capabilities['streaming'] ?? $fallback['streaming'] ?? true),
        ];
    }

    /**
     * @return array{input:list<string>,output:list<string>}
     */
    public function providerModalities(string $provider): array
    {
        $modalities = $this->provider($provider)['modalities'] ?? [];

        return [
            'input' => $this->stringList($modalities['input'] ?? ['text']),
            'output' => $this->stringList($modalities['output'] ?? ['text']),
        ];
    }

    /**
     * @return array{input:list<string>,output:list<string>}
     */
    public function modelModalities(string $provider, string $model): array
    {
        $modelData = $this->model($provider, $model);
        $providerModalities = $this->providerModalities($provider);
        $modalities = is_array($modelData['modalities'] ?? null) ? $modelData['modalities'] : [];

        return [
            'input' => $this->stringList($modalities['input'] ?? $providerModalities['input']),
            'output' => $this->stringList($modalities['output'] ?? $providerModalities['output']),
        ];
    }

    public function supportsAsync(string $provider): bool
    {
        return in_array($this->driver($provider), [
            'openai',
            'openai-compatible',
            'deepseek',
            'groq',
            'mistral',
            'ollama',
            'openrouter',
            'perplexity',
            'xai',
            'z',
            'glm',
            'glm-coding',
            'kimi',
            'kimi-coding',
        ], true);
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

    private function inferDriver(string $provider): string
    {
        return match ($provider) {
            'z-api' => 'glm',
            'z' => 'glm-coding',
            'kimi' => 'kimi',
            'kimi-coding' => 'kimi-coding',
            'minimax' => 'minimax',
            'minimax-cn' => 'minimax-cn',
            'stepfun-plan' => 'openai-compatible',
            'openrouter' => 'openrouter',
            'openai' => 'openai',
            'anthropic' => 'anthropic',
            'gemini' => 'gemini',
            'deepseek' => 'deepseek',
            'groq' => 'groq',
            'mistral' => 'mistral',
            'xai' => 'xai',
            'ollama' => 'ollama',
            'perplexity' => 'perplexity',
            default => 'openai-compatible',
        };
    }
}
