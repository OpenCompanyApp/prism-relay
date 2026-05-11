<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge\LaravelAi;

use Closure;
use Generator;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\SupportsFileSearch;
use Laravel\Ai\Contracts\Providers\SupportsWebFetch;
use Laravel\Ai\Contracts\Providers\SupportsWebSearch;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool as LaravelAiTool;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Tools\Request as ToolRequest;
use OpenCompany\PrismRelay\Bridge\LaravelAiPrismTool;
use OpenCompany\PrismRelay\Bridge\PrismObjectSchema;
use OpenCompany\PrismRelay\Bridge\PrismResponseMapper;
use OpenCompany\PrismRelay\Bridge\SystemPromptBag;
use OpenCompany\PrismRelay\Bridge\ToolAwarePrismMessages;
use OpenCompany\PrismRelay\Relay;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Exceptions\PrismException as PrismVendorException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\ProviderTool as PrismProviderTool;
use RuntimeException;

/**
 * Laravel AI 0.6 text gateway that keeps prism-relay in charge of prompt caching.
 */
class RelayTextGateway implements TextGateway
{
    protected Closure $invokingToolCallback;

    protected Closure $toolInvokedCallback;

    public function __construct(
        protected ?Relay $relay = null,
        protected ?string $forcedProvider = null,
        protected ?int $defaultTimeout = null,
    ) {
        $this->invokingToolCallback = fn () => true;
        $this->toolInvokedCallback = fn () => true;
    }

    public function generateText(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): TextResponse {
        [$request, $structured, $providerName] = $this->createRequest(
            $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout,
        );

        try {
            $response = $request->{$structured ? 'asStructured' : 'asText'}();
        } catch (PrismVendorException $e) {
            throw $e;
        }

        $citations = PrismResponseMapper::citations(
            new Collection($response->additionalContent['citations'] ?? [])
        );

        return $structured
            ? (new StructuredTextResponse(
                $response->structured,
                $response->text,
                PrismResponseMapper::usage($response->usage),
                new Meta($providerName, $response->meta->model, $citations),
            ))->withToolCallsAndResults(
                toolCalls: (new Collection($response->toolCalls))->map(LaravelAiPrismTool::toLaravelToolCall(...)),
                toolResults: (new Collection($response->toolResults))->map(LaravelAiPrismTool::toLaravelToolResult(...)),
            )->withSteps(PrismResponseMapper::steps($response->steps, $providerName))
            : (new TextResponse(
                $response->text,
                PrismResponseMapper::usage($response->usage),
                new Meta($providerName, $response->meta->model, $citations),
            ))->withMessages(
                ToolAwarePrismMessages::toLaravelMessages($response->messages)
            )->withSteps(PrismResponseMapper::steps($response->steps, $providerName));
    }

    public function streamText(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): Generator {
        [$request, , $providerName] = $this->createRequest(
            $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout,
        );

        try {
            foreach ($request->asStream() as $event) {
                if (! is_null($mapped = PrismStreamEventMapper::toLaravel($invocationId, $event, $providerName, $model))) {
                    yield $mapped;
                }
            }
        } catch (PrismVendorException $e) {
            throw $e;
        }
    }

    public function onToolInvocation(Closure $invoking, Closure $invoked): self
    {
        $this->invokingToolCallback = $invoking;
        $this->toolInvokedCallback = $invoked;

        return $this;
    }

    protected function createRequest(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
    ): array {
        $providerKey = $this->providerKey($provider);
        $providerName = $this->providerName($provider);
        $prismMessages = ToolAwarePrismMessages::fromLaravelMessages(new Collection($messages))->all();
        $prismTools = $this->mapTools($tools);

        $plan = $this->relay()->planPromptCache(
            provider: $providerKey,
            model: $model,
            systemPrompts: $this->resolveSystemPrompts($instructions),
            messages: $prismMessages,
            tools: $prismTools,
        );

        $request = tap(
            ! empty($schema) ? Prism::structured() : Prism::text(),
            fn ($prism) => $prism->using($providerKey, $model, $this->providerConfig($provider))
        )
            ->withSystemPrompts($plan->systemPrompts)
            ->withMessages($plan->messages)
            ->withProviderOptions(array_merge(
                $plan->providerOptions,
                $this->providerOptions($provider, $options),
            ))
            ->withClientOptions(['timeout' => $timeout ?? $this->defaultTimeout ?? $this->configuredTimeout()]);

        if (! empty($schema)) {
            $request->withSchema(new PrismObjectSchema($schema));
        }

        if (! is_null($options?->maxTokens)) {
            $request->withMaxTokens($options->maxTokens);
        }

        if (! is_null($options?->temperature)) {
            $request->usingTemperature($options->temperature);
        }

        if (! is_null($options?->topP)) {
            $request->usingTopP($options->topP);
        }

        if ($plan->tools !== []) {
            $request
                ->withTools($plan->tools)
                ->withToolChoice(ToolChoice::Auto)
                ->withMaxSteps($options?->maxSteps ?? max(1, (int) round(count($plan->tools) * 1.5)));
        }

        if ($providerTools = $this->providerTools($provider, $tools)) {
            $request->withProviderTools($providerTools);
        }

        if (method_exists($request, 'withClientRetry')) {
            $request->withClientRetry(
                times: 2,
                sleepMilliseconds: 1000,
                when: fn ($exception) => $this->shouldRetry($exception),
                throw: true,
            );
        }

        return [$request, ! empty($schema), $providerName];
    }

    /**
     * @return array<int, \Prism\Prism\ValueObjects\Messages\SystemMessage>
     */
    protected function resolveSystemPrompts(?string $instructions): array
    {
        $bag = function_exists('app') && app()->bound(SystemPromptBag::class)
            ? app(SystemPromptBag::class)
            : null;

        if ($bag !== null && $bag->hasPrompts()) {
            return $bag->toPrismSystemMessages();
        }

        if (! empty($instructions)) {
            return (new SystemPromptBag([$instructions]))->toPrismSystemMessages();
        }

        return [];
    }

    /**
     * @param  array<int, mixed>  $tools
     * @return array<int, \Prism\Prism\Tool>
     */
    protected function mapTools(array $tools): array
    {
        return (new Collection($tools))->map(function ($tool) {
            return ! $tool instanceof ProviderTool ? $this->createPrismTool($tool) : null;
        })->filter()->values()->all();
    }

    protected function createPrismTool(LaravelAiTool $tool): LaravelAiPrismTool
    {
        $toolName = method_exists($tool, 'name')
            ? $tool->name()
            : class_basename($tool);

        $schema = $tool->schema(new JsonSchemaTypeFactory);

        return (new LaravelAiPrismTool)
            ->as($toolName)
            ->for((string) $tool->description())
            ->when(
                ! empty($schema),
                fn ($prismTool) => $prismTool->withParameter(new PrismObjectSchema($schema)),
            )
            ->using(fn ($arguments) => $this->invokeTool($tool, $arguments))
            ->withoutErrorHandling();
    }

    protected function invokeTool(LaravelAiTool $tool, array $arguments): string
    {
        $arguments = $arguments['schema_definition'] ?? $arguments;

        call_user_func($this->invokingToolCallback, $tool, $arguments);

        return (string) tap(
            $tool->handle(new ToolRequest($arguments)),
            fn ($result) => call_user_func($this->toolInvokedCallback, $tool, $arguments, $result),
        );
    }

    /**
     * @param  array<int, mixed>  $tools
     * @return array<int, PrismProviderTool>
     */
    protected function providerTools(TextProvider $provider, array $tools): array
    {
        return (new Collection($tools))->map(function ($tool) use ($provider) {
            return match (true) {
                $tool instanceof FileSearch => $this->fileSearchTool($provider, $tool),
                $tool instanceof WebFetch => $this->webFetchTool($provider, $tool),
                $tool instanceof WebSearch => $this->webSearchTool($provider, $tool),
                default => null,
            };
        })->filter()->values()->all();
    }

    protected function fileSearchTool(TextProvider $provider, FileSearch $tool): PrismProviderTool
    {
        $options = $provider instanceof SupportsFileSearch
            ? $provider->fileSearchToolOptions($tool)
            : throw new RuntimeException('Provider ['.$this->providerName($provider).'] does not support file search.');

        return match ($this->providerKey($provider)) {
            'openai', 'codex' => new PrismProviderTool('file_search', options: $options),
            'gemini' => new PrismProviderTool('fileSearch', options: $options),
            default => throw new RuntimeException('Provider ['.$this->providerName($provider).'] does not support file search.'),
        };
    }

    protected function webFetchTool(TextProvider $provider, WebFetch $tool): PrismProviderTool
    {
        $options = $provider instanceof SupportsWebFetch
            ? $provider->webFetchToolOptions($tool)
            : throw new RuntimeException('Provider ['.$this->providerName($provider).'] does not support web fetch.');

        return match ($this->providerKey($provider)) {
            'anthropic' => new PrismProviderTool('web_fetch_20250910', 'web_fetch', options: $options),
            'gemini' => new PrismProviderTool('url_context'),
            default => throw new RuntimeException('Provider ['.$this->providerName($provider).'] does not support web fetch.'),
        };
    }

    protected function webSearchTool(TextProvider $provider, WebSearch $tool): PrismProviderTool
    {
        $options = $provider instanceof SupportsWebSearch
            ? $provider->webSearchToolOptions($tool)
            : throw new RuntimeException('Provider ['.$this->providerName($provider).'] does not support web search.');

        return match ($this->providerKey($provider)) {
            'anthropic' => new PrismProviderTool('web_search_20250305', 'web_search', options: $options),
            'gemini' => new PrismProviderTool('google_search'),
            'openai', 'codex' => new PrismProviderTool('web_search', options: $options),
            default => throw new RuntimeException('Provider ['.$this->providerName($provider).'] does not support web search.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function providerConfig(TextProvider $provider): array
    {
        $config = method_exists($provider, 'additionalConfiguration')
            ? $provider->additionalConfiguration()
            : [];

        $credentials = method_exists($provider, 'providerCredentials')
            ? $provider->providerCredentials()
            : [];

        return array_filter([
            ...$config,
            'api_key' => $credentials['key'] ?? $config['api_key'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function providerOptions(TextProvider $provider, ?TextGenerationOptions $options): array
    {
        if ($options === null || ! method_exists($options, 'providerOptions')) {
            return [];
        }

        return $options->providerOptions($this->providerKey($provider)) ?? [];
    }

    protected function providerKey(TextProvider $provider): string
    {
        if ($this->forcedProvider !== null) {
            return $this->forcedProvider;
        }

        if (method_exists($provider, 'driver')) {
            return (string) $provider->driver();
        }

        if (method_exists($provider, 'name')) {
            return (string) $provider->name();
        }

        throw new RuntimeException('Unable to determine provider driver.');
    }

    protected function providerName(TextProvider $provider): string
    {
        return method_exists($provider, 'name')
            ? (string) $provider->name()
            : $this->providerKey($provider);
    }

    protected function relay(): Relay
    {
        return $this->relay ??= function_exists('app') && app()->bound(Relay::class)
            ? app(Relay::class)
            : new Relay;
    }

    protected function configuredTimeout(): int
    {
        return function_exists('config') ? (int) config('prism.request_timeout', 600) : 600;
    }

    protected function shouldRetry(mixed $exception): bool
    {
        if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        return $exception instanceof \Illuminate\Http\Client\RequestException
            && in_array($exception->response->status(), [408, 429, 500, 502, 503, 504], true);
    }
}

