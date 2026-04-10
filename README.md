# prism-relay

`opencompanyapp/prism-relay` is the shared provider-runtime layer used around Prism PHP in the OpenCompany ecosystem.

It is not just a bundle of custom providers. The package currently handles:

- provider registration into Prism
- a canonical provider and model metadata registry
- provider aliases and model aliases
- prompt-caching strategy selection
- cache-aware Laravel AI -> Prism gateway bridging
- tool-call and tool-result message preservation
- OpenAI-compatible message mapping
- tool-call normalization
- structured provider error normalization
- provider capability lookup
- provider reasoning strategy lookup

In practice, this package is the place where provider-specific behavior should live when that behavior is not unique to the OpenCompany app itself.

## What prism-relay is for

Use `prism-relay` when you want one shared place to answer questions like:

- What providers do we know about?
- What aliases map to what canonical provider names?
- What is the default model for a provider?
- What context window, max output, or pricing data do we have for a model?
- Does this provider support prompt caching, and if so, how?
- Does this provider support `temperature`, `top_p`, `stream_usage`, or explicit reasoning effort?
- How should Laravel AI messages be converted so tool-call and tool-result history survives?
- How should Prism responses or provider exceptions be normalized before app code consumes them?

This package is especially useful when multiple applications or runtimes need consistent provider behavior instead of each app reinventing its own provider table, cache rules, and gateway patches.

## What prism-relay is not

`prism-relay` is not:

- a full application framework
- a complete replacement for Prism PHP
- a persistence layer
- an integration settings UI
- an API key store
- an app-specific provider resolver

For example, OpenCompany's workspace-aware `DynamicProviderResolver` belongs in the app because it decides how workspace config is applied. The shared provider metadata, aliasing, and cache behavior belong in `prism-relay`.

## Package structure

Current source layout:

- `src/PrismRelayServiceProvider.php`
- `src/RelayManager.php`
- `src/Relay.php`
- `src/Registry/*`
- `src/Meta/*`
- `src/Caching/*`
- `src/Bridge/*`
- `src/Providers/*`
- `src/Capabilities/*`
- `src/Reasoning/*`
- `src/Normalizers/*`
- `src/Errors/*`
- `src/Support/*`
- `config/relay.php`
- `config/relay.generated.php`
- `config/relay.manual.php`
- `scripts/sync-registry.mjs`

The package is easier to understand if you treat it as five layers:

1. Registry and metadata
2. Provider registration
3. Gateway and prompt-cache bridging
4. Message / tool / error normalization
5. Provider capability and reasoning strategy helpers

## Installation

Add the package in Composer:

```bash
composer require opencompanyapp/prism-relay
```

The package declares a Laravel service provider in `composer.json`, so in a Laravel app it can be auto-discovered:

- `OpenCompany\\PrismRelay\\PrismRelayServiceProvider`

### Runtime dependencies

The package requires:

- PHP `^8.2`
- `prism-php/prism`
- `psr/log`

Important note:

- The bridge classes under `src/Bridge/` are intended for Laravel + Laravel AI consumers.
- If you use `CachingPrismGateway` or `SystemPromptBag`, your app should already be using Laravel AI and Prism in the same runtime.

## Core concepts

### 1. RelayRegistry

`RelayRegistry` is the canonical in-memory representation of provider metadata.

It knows:

- canonical provider names
- provider aliases
- registration names
- model metadata
- model aliases
- default models
- auth modes
- supported capabilities
- provider and model modalities

Representative API:

```php
use OpenCompany\PrismRelay\Registry\RelayRegistryBuilder;

$registry = (new RelayRegistryBuilder)->build();

$registry->canonicalProvider('z.ai');      // "z-api"
$registry->driver('kimi-coding');          // "kimi-coding"
$registry->defaultModel('openai');         // via provider metadata
$registry->model('anthropic', 'claude-sonnet-4-5-20250929');
$registry->registrationNames();            // canonical names + eligible aliases
```

### 2. RelayRegistryBuilder

`RelayRegistryBuilder` constructs a registry by merging multiple sources.

Current merge flow:

1. bundled generated snapshot
2. bundled manual overrides
3. live provider data from `models.dev` when enabled
4. app overrides passed into the builder

The builder also supports import-style overrides through `models_dev_provider`.

This means consumers can use:

- a fully bundled snapshot
- a bundled snapshot plus live metadata
- app-specific overlays without forking the package

Examples:

```php
use OpenCompany\PrismRelay\Registry\RelayRegistryBuilder;

$builder = new RelayRegistryBuilder;

$bundled = $builder->buildBundled();
$full = $builder->build();

$custom = $builder->build([
    'my-provider' => [
        'models_dev_provider' => 'openai',
        'url' => 'https://example.com/v1',
        'driver' => 'openai-compatible',
        'auth' => 'api_key',
    ],
]);
```

### 3. ProviderMeta

`ProviderMeta` is the read-friendly API over the registry.

It exposes:

- default model lookup
- provider URL lookup
- context window lookup
- max output lookup
- detailed model info
- pricing
- cache capability
- thinking support
- model lists

Example:

```php
use OpenCompany\PrismRelay\Meta\ProviderMeta;

$meta = new ProviderMeta;

$meta->defaultModel('openai');
$meta->url('anthropic');
$meta->contextWindow('gemini', 'gemini-2.0-flash');
$meta->maxOutput('openai', 'gpt-4o');
$meta->supportsThinking('deepseek', 'deepseek-reasoner');

$info = $meta->modelInfo('anthropic', 'claude-sonnet-4-5-20250929');

$info->contextWindow;
$info->inputPricePerMillion;
$info->cacheCapability;
```

## Provider registration

### PrismRelayServiceProvider

In Laravel, the service provider does three main things:

- binds `RelayRegistryBuilder`
- binds a singleton `RelayRegistry`
- binds a singleton `RelayManager`
- binds a singleton `Relay`
- registers providers into Prism after `PrismManager` resolves

That means the package is designed to become the shared provider-registration layer instead of every app hand-registering provider variants.

### RelayManager

`RelayManager` is responsible for taking registry entries and registering them with Prism.

It handles several categories of provider drivers:

- custom direct adapters
  - `glm`
  - `glm-coding`
  - `kimi`
  - `kimi-coding`
  - `minimax`
  - `minimax-cn`
- model router providers
- unsupported or externally mediated transports
  - `codex`
  - `external-process`
  - `google-vertex`
  - `amazon-bedrock`
- native Prism-compatible providers
  - `openai`
  - `anthropic`
  - `deepseek`
  - `gemini`
  - `groq`
  - `mistral`
  - `ollama`
  - `openrouter`
  - `perplexity`
  - `xai`
  - `z`

This is one of the most important current facts about the package:

- `prism-relay` is no longer only about custom OpenCompany providers
- it now also acts as a normalized registration layer around native providers and aliases

That is useful, but it also means consumers should be careful not to treat `isRelayProvider()` as a synonym for “OpenCompany custom provider only.”

## Prompt caching

Prompt caching is one of the main reasons this package exists.

### CacheStrategy

`CacheStrategy` answers:

- does the provider support caching?
- what kind of caching?
- what provider options should be added?
- what per-message annotations should be added?
- does the provider report cache metrics?
- does the provider require explicit opt-in?

Current cache capability mapping:

- `anthropic` -> ephemeral
- `openrouter` -> ephemeral
- `openai` -> auto
- `gemini` -> dedicated
- everything else -> none

The package models this using:

- `CacheCapability`
- `CacheStrategy`
- `PromptCachePlanner`
- `PromptCacheOrchestrator`
- `PromptCachePlan`

### CachingPrismGateway

`src/Bridge/CachingPrismGateway.php` is the main Laravel AI bridge entrypoint.

It is a drop-in `PrismGateway` replacement that:

- resolves system prompts from a `SystemPromptBag` when present
- falls back to the standard single instructions string
- converts Laravel AI messages into Prism messages
- preserves assistant tool calls and tool-result messages via `ToolAwarePrismMessages`
- asks `Relay::planPromptCache()` how to annotate the request
- writes system prompts and provider options onto the Prism request

This is the package-level answer to “how do we make prompt caching work without app-specific gateway hacks?”

Example use in a Laravel AI provider:

```php
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Providers\OpenAiProvider;
use OpenCompany\PrismRelay\Bridge\CachingPrismGateway;

$gateway = new CachingPrismGateway(app(Dispatcher::class));

$provider = new OpenAiProvider(
    $gateway,
    ['driver' => 'openai', 'key' => env('OPENAI_API_KEY')],
    app(Dispatcher::class),
);
```

### SystemPromptBag

`SystemPromptBag` exists so applications can split system prompts into multiple stable/volatile parts before the gateway plans caching.

That lets the cache planner reason over structured system prompt segments instead of a single opaque string.

Use it when your app has prompt frames like:

- stable product instructions
- workspace instructions
- project instructions
- volatile task instructions

## Message conversion and normalization

### ToolAwarePrismMessages

This package now contains `ToolAwarePrismMessages`, which extends the Laravel AI Prism message bridge so that it preserves:

- assistant tool calls
- tool-result messages

This matters because the default Laravel AI static bridge path can be lossy for tool-history-rich runtimes.

If your runtime relies on:

- retries
- checkpoint replay
- prompt cache replay
- continuation after tool use

then losing tool-call structure during conversion is a real correctness bug, not just a cosmetic mismatch.

### OpenAiCompatibleMessageMapper

`OpenAiCompatibleMessageMapper` converts Prism messages into OpenAI-style payload arrays for providers or transports that expect that schema.

It handles:

- system messages
- user messages
- assistant messages
- assistant tool calls
- tool-result messages
- image content
- OpenRouter cache-control annotations

This is useful when a provider is not a native Prism transport but still wants OpenAI-compatible message payloads.

### ToolCallNormalizer

`ToolCallNormalizer` exists to sanitize tool calls coming back from providers so downstream code has one normalized shape.

This reduces provider-specific weirdness leaking into application code.

## Error normalization

`Relay::normalizeError()` delegates to `ErrorNormalizer` and returns a structured `ProviderError`.

This allows applications to reason over provider failures in a more coherent way than “catch everything and parse strings.”

Package pieces involved:

- `Errors/ProviderError.php`
- `Errors/ErrorCategory.php`
- `Normalizers/ErrorNormalizer.php`

The package also supports listener hooks so consumers can observe requests, responses, and normalized errors through `RelayListener`.

## Provider capabilities

`ProviderCapabilities` answers whether a provider supports request parameters such as:

- `temperature`
- `top_p`
- `max_tokens`
- streaming
- stream usage reporting

This is useful for apps that want to strip unsupported parameters before sending requests.

Why it matters:

- some providers reject `temperature`
- some reject `top_p`
- some support streaming but not usage-in-stream metadata
- some local backends behave badly if you send unsupported options

Example:

```php
use OpenCompany\PrismRelay\Capabilities\ProviderCapabilities;

$caps = ProviderCapabilities::for('codex');

if ($caps->supportsTemperature()) {
    // send temperature
}
```

## Reasoning strategy

`ReasoningStrategy` is the provider-level companion to model metadata.

It answers questions like:

- is reasoning unsupported?
- is reasoning always-on?
- does the provider accept an explicit reasoning effort parameter?
- how do we extract reasoning text from the provider response?

Current examples:

- `openai` and `xai` use explicit effort-style reasoning parameters
- several providers are modeled as always-on reasoning providers
- unsupported providers return no reasoning params

This prevents every application from reinventing provider-specific reasoning logic.

## Metadata sources

The registry is built from a combination of static and live sources.

### Bundled files

- `config/relay.php`
- `config/relay.generated.php`
- `config/relay.manual.php`

`relay.php` merges generated metadata with manual overrides.

### Live metadata

`ModelsDevClient` can fetch provider metadata from:

- `https://models.dev/api.json`

The builder can merge that live data into the final registry.

This allows the package to:

- keep a bundled snapshot for deterministic use
- layer in newer metadata when desired
- avoid hardcoding every provider detail directly in app code

## Registry sync workflow

The package includes:

```bash
composer run sync-registry
```

This runs:

- `scripts/sync-registry.mjs`

That script currently pulls and merges model/provider data from:

- an opencode snapshot
- Hermes model/auth sources
- package-specific alias and driver maps
- package-specific capability overrides

The goal is to keep `relay.generated.php` updated without manually editing every provider/model entry by hand.

This is best thought of as a metadata build step, not a runtime dependency.

## Extending the package

You can extend `prism-relay` in several ways.

### 1. App overrides via RelayRegistryBuilder

```php
use OpenCompany\PrismRelay\Registry\RelayRegistryBuilder;

$registry = (new RelayRegistryBuilder)->build([
    'my-proxy' => [
        'driver' => 'openai-compatible',
        'url' => 'https://proxy.example.com/v1',
        'default_model' => 'gpt-4o',
        'models' => [
            'gpt-4o' => [
                'context' => 128000,
                'max_output' => 16384,
            ],
        ],
    ],
]);
```

### 2. Manual provider metadata

If the provider needs to ship with the package itself, add or override it in:

- `config/relay.manual.php`

### 3. Listener hooks

Applications can bind `RelayListener` to observe:

- before request
- after response
- normalized error handling

### 4. Package-level bridge logic

If the behavior is generic across consumers, prefer putting it here rather than into app code.

Examples of good package ownership:

- message conversion correctness
- cache strategy rules
- provider alias handling
- error classification
- OpenAI-compatible payload mapping

Examples of app ownership:

- workspace-specific credential lookup
- app-specific fallback rules
- app-specific policy for choosing providers

## Laravel usage example

Typical Laravel usage looks like:

1. install the package
2. let the service provider register into the container
3. use `RelayManager` / `ProviderMeta` / `Relay`
4. wire `CachingPrismGateway` into your Laravel AI provider creation

Example:

```php
use Laravel\Ai\AiManager;
use Laravel\Ai\Providers\OpenAiProvider;
use OpenCompany\PrismRelay\Bridge\CachingPrismGateway;

app()->afterResolving(AiManager::class, function (AiManager $aiManager, $app) {
    $gateway = new CachingPrismGateway($app['events']);

    $aiManager->extend('openai', fn ($app, array $config) => new OpenAiProvider(
        $gateway,
        $config,
        $app['events'],
    ));
});
```

## Non-Laravel usage

The registry, metadata, strategy, normalization, and relay classes are also usable directly without Laravel auto-discovery.

Example:

```php
use OpenCompany\PrismRelay\Meta\ProviderMeta;
use OpenCompany\PrismRelay\Relay;
use OpenCompany\PrismRelay\Registry\RelayRegistryBuilder;

$registry = (new RelayRegistryBuilder)->build();
$meta = new ProviderMeta($registry);
$relay = new Relay(providerMeta: $meta);

$meta->models('openai');
$meta->modelInfo('anthropic', 'claude-sonnet-4-5-20250929');
```

The Laravel-specific bridge pieces are the main area that assume a Laravel runtime.

## Current boundaries and caveats

- `prism-relay` is the shared provider-runtime layer; it should not absorb app-specific credential or workspace policy logic.
- Some bridge classes depend on Laravel AI conventions even though the package itself is centered on Prism.
- The registry can include native and custom providers, so “known to relay” does not necessarily mean “custom provider only.”
- Live metadata and bundled metadata can diverge; decide intentionally whether your app wants bundled-only or live-augmented builds.
- This package is best when treated as infrastructure, not as a dumping ground for app-specific fixes.

## When to change prism-relay vs the app

Change `prism-relay` when the issue is about:

- provider metadata
- provider aliases
- model aliases
- cache strategy
- message conversion
- shared gateway behavior
- tool-call normalization
- provider capability lookup
- reasoning strategy
- OpenAI-compatible payload mapping

Change the app when the issue is about:

- how credentials are loaded
- how a workspace chooses a provider
- how a user or tenant is mapped to provider config
- app-specific fallbacks
- app-specific runtime orchestration

## License

MIT
