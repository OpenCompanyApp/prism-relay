<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay;

use OpenCompany\PrismRelay\Contracts\RelayListener;
use OpenCompany\PrismRelay\Errors\ProviderError;
use OpenCompany\PrismRelay\Meta\ProviderMeta;
use OpenCompany\PrismRelay\Normalizers\ErrorNormalizer;
use OpenCompany\PrismRelay\Normalizers\ToolCallNormalizer;
use OpenCompany\PrismRelay\Support\NullRelayListener;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\Text\Step;

class Relay
{
    private ProviderMeta $providerMeta;

    public function __construct(
        private RelayListener $listener = new NullRelayListener,
    ) {
        $this->providerMeta = new ProviderMeta;
    }

    /**
     * Normalize tool calls in a Prism TextResponse.
     *
     * Creates a new Response with sanitized tool calls in both
     * the top-level toolCalls array and each Step's toolCalls.
     */
    public function normalizeResponse(string $provider, string $model, TextResponse $response): TextResponse
    {
        $this->listener->afterResponse($provider, $model, $response->usage, $response->meta);

        $normalizedToolCalls = ToolCallNormalizer::normalize($response->toolCalls);

        $normalizedSteps = $response->steps->map(fn (Step $step) => new Step(
            text: $step->text,
            finishReason: $step->finishReason,
            toolCalls: ToolCallNormalizer::normalize($step->toolCalls),
            toolResults: $step->toolResults,
            providerToolCalls: $step->providerToolCalls,
            usage: $step->usage,
            meta: $step->meta,
            messages: $step->messages,
            systemPrompts: $step->systemPrompts,
            additionalContent: $step->additionalContent,
            raw: $step->raw,
        ));

        return new TextResponse(
            steps: $normalizedSteps,
            text: $response->text,
            finishReason: $response->finishReason,
            toolCalls: $normalizedToolCalls,
            toolResults: $response->toolResults,
            usage: $response->usage,
            meta: $response->meta,
            messages: $response->messages,
            additionalContent: $response->additionalContent,
            raw: $response->raw,
        );
    }

    /**
     * Normalize a Prism exception into a structured ProviderError.
     */
    public function normalizeError(\Throwable $e, string $provider, string $model): ProviderError
    {
        $error = ErrorNormalizer::normalize($e, $provider, $model);
        $this->listener->onError($error);

        return $error;
    }

    /**
     * Fire the beforeRequest listener hook.
     */
    public function beforeRequest(string $provider, string $model): void
    {
        $this->listener->beforeRequest($provider, $model);
    }

    /**
     * Access provider metadata (models, context windows, pricing).
     */
    public function meta(): ProviderMeta
    {
        return $this->providerMeta;
    }
}
