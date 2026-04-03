<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay;

use InvalidArgumentException;
use OpenCompany\PrismRelay\Providers\Glm;
use OpenCompany\PrismRelay\Providers\GlmCoding;
use OpenCompany\PrismRelay\Providers\Kimi;
use OpenCompany\PrismRelay\Providers\KimiCoding;
use OpenCompany\PrismRelay\Providers\MiniMax;
use OpenCompany\PrismRelay\Providers\MiniMaxCn;
use OpenCompany\PrismRelay\Providers\ModelRouter;
use OpenCompany\PrismRelay\Providers\UnsupportedTransportProvider;
use OpenCompany\PrismRelay\Registry\RelayRegistry;
use OpenCompany\PrismRelay\Registry\RelayRegistryBuilder;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Anthropic\Anthropic;
use Prism\Prism\Providers\DeepSeek\DeepSeek;
use Prism\Prism\Providers\Gemini\Gemini;
use Prism\Prism\Providers\Groq\Groq;
use Prism\Prism\Providers\Mistral\Mistral;
use Prism\Prism\Providers\Ollama\Ollama;
use Prism\Prism\Providers\OpenAI\OpenAI;
use Prism\Prism\Providers\OpenRouter\OpenRouter;
use Prism\Prism\Providers\Perplexity\Perplexity;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Providers\XAI\XAI;
use Prism\Prism\Providers\Z\Z;

class RelayManager
{
    private RelayRegistry $registry;

    /**
     * @param  array<string, array<string, mixed>>|RelayRegistry|null  $providers
     */
    public function __construct(array|RelayRegistry|null $providers = null)
    {
        if ($providers instanceof RelayRegistry) {
            $this->registry = $providers;

            return;
        }

        $this->registry = $providers !== null
            ? new RelayRegistry($providers)
            : (new RelayRegistryBuilder)->build();
    }

    /**
     * Register all relay providers with a PrismManager instance.
     */
    public function register(PrismManager $manager): void
    {
        foreach ($this->registry->registrationNames() as $name) {
            $manager->extend($name, function ($app, array $config) use ($name) {
                return $this->resolveProvider($name, $config);
            });
        }
    }

    /**
     * Get list of relay-registered provider names.
     *
     * @return string[]
     */
    public function providers(): array
    {
        return $this->registry->registrationNames();
    }

    /**
     * Check if a provider name is managed by relay.
     */
    public function isRelayProvider(string $name): bool
    {
        return in_array($name, $this->registry->registrationNames(), true);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveProvider(string $name, array $config): Provider
    {
        $providerName = $this->registry->canonicalProvider($name) ?? $name;
        $provider = $this->registry->provider($providerName) ?? [];
        $driver = $this->registry->driver($providerName);

        return match ($driver) {
            'glm' => Glm::create($config['api_key'] ?? '', $config['url'] ?? $provider['url'] ?? null),
            'glm-coding' => GlmCoding::create($config['api_key'] ?? '', $config['url'] ?? $provider['url'] ?? null),
            'kimi' => Kimi::create($config['api_key'] ?? '', $config['url'] ?? $provider['url'] ?? null),
            'kimi-coding' => KimiCoding::create($config['api_key'] ?? '', $config['url'] ?? $provider['url'] ?? null),
            'minimax' => MiniMax::create($config['api_key'] ?? '', $config['url'] ?? $provider['url'] ?? null),
            'minimax-cn' => MiniMaxCn::create($config['api_key'] ?? '', $config['url'] ?? $provider['url'] ?? null),
            'model-router' => $this->createModelRouter($provider, $config),
            'unsupported', 'external-process', 'google-vertex', 'amazon-bedrock', 'codex' => new UnsupportedTransportProvider(
                provider: $providerName,
                transport: (string) $driver,
            ),
            default => $this->createNativeProvider((string) $driver, $provider, $config),
        };
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $config
     */
    private function createModelRouter(array $provider, array $config): Provider
    {
        $routes = [];

        foreach ($provider['model_routes'] ?? [] as $route) {
            if (! is_array($route)) {
                continue;
            }

            $routes[] = [
                'match' => is_array($route['match'] ?? null) ? $route['match'] : [],
                'provider' => $this->createNativeProvider(
                    (string) ($route['driver'] ?? 'openai-compatible'),
                    $provider,
                    array_merge($config, is_array($route['config'] ?? null) ? $route['config'] : []),
                ),
            ];
        }

        return new ModelRouter(
            routes: $routes,
            fallback: $this->createNativeProvider(
                (string) ($provider['fallback_driver'] ?? 'openai-compatible'),
                $provider,
                $config,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $config
     */
    private function createNativeProvider(string $driver, array $provider, array $config): Provider
    {
        $url = $config['url'] ?? $provider['url'] ?? '';

        return match ($driver) {
            'openai', 'openai-compatible' => new OpenAI(
                apiKey: $config['api_key'] ?? '',
                url: $url,
                organization: $config['organization'] ?? null,
                project: $config['project'] ?? null,
            ),
            'anthropic', 'anthropic-compatible' => new Anthropic(
                apiKey: $config['api_key'] ?? '',
                apiVersion: $config['version'] ?? $provider['version'] ?? '2023-06-01',
                url: $url ?: 'https://api.anthropic.com/v1',
                betaFeatures: $config['anthropic_beta'] ?? $provider['anthropic_beta'] ?? null,
            ),
            'deepseek' => new DeepSeek(
                apiKey: $config['api_key'] ?? '',
                url: $url,
            ),
            'gemini' => new Gemini(
                apiKey: $config['api_key'] ?? '',
                url: $url,
            ),
            'groq' => new Groq(
                apiKey: $config['api_key'] ?? '',
                url: $url,
            ),
            'mistral' => new Mistral(
                apiKey: $config['api_key'] ?? '',
                url: $url,
            ),
            'ollama' => new Ollama(
                apiKey: $config['api_key'] ?? '',
                url: $url,
            ),
            'openrouter' => new OpenRouter(
                apiKey: $config['api_key'] ?? '',
                url: $url ?: 'https://openrouter.ai/api/v1',
                httpReferer: $config['site']['http_referer'] ?? $provider['site']['http_referer'] ?? null,
                xTitle: $config['site']['x_title'] ?? $provider['site']['x_title'] ?? null,
            ),
            'perplexity' => new Perplexity(
                apiKey: $config['api_key'] ?? '',
                url: $url,
            ),
            'xai' => new XAI(
                apiKey: $config['api_key'] ?? '',
                url: $url,
            ),
            'z' => new Z(
                apiKey: $config['api_key'] ?? '',
                url: $url,
            ),
            default => throw new InvalidArgumentException(sprintf('Unsupported relay driver [%s].', $driver)),
        };
    }

}
