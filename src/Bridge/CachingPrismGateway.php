<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge;

use Generator;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\Prism\PrismCitations;
use Laravel\Ai\Gateway\Prism\PrismException;
use Laravel\Ai\Gateway\Prism\PrismGateway;
use Laravel\Ai\Gateway\Prism\PrismMessages;
use Laravel\Ai\Gateway\Prism\PrismSteps;
use Laravel\Ai\Gateway\Prism\PrismStreamEvent;
use Laravel\Ai\Gateway\Prism\PrismTool;
use Laravel\Ai\Gateway\Prism\PrismUsage;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use OpenCompany\PrismRelay\Relay;
use Prism\Prism\Exceptions\PrismException as PrismVendorException;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

/**
 * Drop-in PrismGateway replacement that applies provider-aware prompt caching.
 *
 * Reads split system prompts from a request-scoped SystemPromptBag (if present)
 * and delegates cache planning to Relay::planPromptCache(). When no bag is bound
 * in the container, falls back to vanilla single-instruction behavior.
 *
 * This allows prompt caching to work without any modifications to laravel/ai's
 * interfaces, traits, or gateway contracts.
 */
class CachingPrismGateway extends PrismGateway
{
    /**
     * {@inheritdoc}
     */
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
        [$request, $structured] = [
            $this->createPrismTextRequest($provider, $model, $schema, $options, $timeout),
            ! empty($schema),
        ];

        $prismMessages = $this->applyCachePlanning(
            $request, $provider->name(), $model, $instructions, $messages,
        );

        if (count($tools) > 0) {
            $this->addTools($request, $tools, $options);
            $this->addProviderTools($provider, $request, $tools, $options);
        }

        try {
            $response = $request
                ->withMessages($prismMessages)
                ->{$structured ? 'asStructured' : 'asText'}();
        } catch (PrismVendorException $e) {
            throw PrismException::toAiException($e, $provider, $model);
        }

        $citations = PrismCitations::toLaravelCitations(
            new Collection($response->additionalContent['citations'] ?? [])
        );

        return $structured
            ? (new StructuredTextResponse(
                $response->structured,
                $response->text,
                PrismUsage::toLaravelUsage($response->usage),
                new Meta($provider->name(), $response->meta->model, $citations),
            ))->withToolCallsAndResults(
                toolCalls: (new Collection($response->toolCalls))->map(PrismTool::toLaravelToolCall(...)),
                toolResults: (new Collection($response->toolResults))->map(PrismTool::toLaravelToolResult(...)),
            )->withSteps(PrismSteps::toLaravelSteps($response->steps, $provider))
            : (new TextResponse(
                $response->text,
                PrismUsage::toLaravelUsage($response->usage),
                new Meta($provider->name(), $response->meta->model, $citations),
            ))->withMessages(
                PrismMessages::toLaravelMessages($response->messages)
            )->withSteps(PrismSteps::toLaravelSteps($response->steps, $provider));
    }

    /**
     * {@inheritdoc}
     */
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
        [$request, $structured] = [
            $this->createPrismTextRequest($provider, $model, $schema, $options, $timeout),
            ! empty($schema),
        ];

        $prismMessages = $this->applyCachePlanning(
            $request, $provider->name(), $model, $instructions, $messages,
        );

        if (count($tools) > 0) {
            $this->addTools($request, $tools, $options);
            $this->addProviderTools($provider, $request, $tools, $options);
        }

        try {
            $events = $request
                ->withMessages($prismMessages)
                ->asStream();

            foreach ($events as $event) {
                if (! is_null($event = PrismStreamEvent::toLaravelStreamEvent(
                    $invocationId, $event, $provider->name(), $model
                ))) {
                    yield $event;
                }
            }
        } catch (PrismVendorException $e) {
            throw PrismException::toAiException($e, $provider, $model);
        }
    }

    /**
     * Resolve system prompts (from bag or instructions), plan caching,
     * and apply annotations to the Prism request.
     *
     * @return array<int, \Prism\Prism\Contracts\Message>  Cache-annotated Prism messages
     */
    protected function applyCachePlanning(
        mixed $request,
        string $providerName,
        string $model,
        ?string $instructions,
        array $messages,
    ): array {
        $systemPrompts = $this->resolveSystemPrompts($instructions);
        $prismMessages = $this->toPrismMessages($messages);

        if ($systemPrompts === []) {
            return $prismMessages;
        }

        $plan = (new Relay)->planPromptCache(
            $providerName, $model, $systemPrompts, $prismMessages,
        );

        $request->withSystemPrompts($plan->systemPrompts);

        if ($plan->providerOptions !== []) {
            $request->withProviderOptions(array_merge(
                $request->providerOptions(),
                $plan->providerOptions,
            ));
        }

        return $plan->messages;
    }

    /**
     * Preserve assistant tool calls and tool results when converting Laravel AI messages.
     */
    protected function toPrismMessages(array $messages): array
    {
        return ToolAwarePrismMessages::fromLaravelMessages(new Collection($messages))->all();
    }

    /**
     * Build Prism SystemMessage array from the SystemPromptBag (preferred)
     * or fall back to the single instructions string.
     *
     * @return SystemMessage[]
     */
    protected function resolveSystemPrompts(?string $instructions): array
    {
        $bag = app()->bound(SystemPromptBag::class)
            ? app(SystemPromptBag::class)
            : null;

        if ($bag !== null && $bag->hasPrompts()) {
            return $bag->toPrismSystemMessages();
        }

        if (! empty($instructions)) {
            return [new SystemMessage($instructions)];
        }

        return [];
    }
}
