<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge\LaravelAi;

use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\ProviderToolEvent as LaravelProviderToolEvent;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\ReasoningEnd;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamEvent as LaravelStreamEvent;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use OpenCompany\PrismRelay\Bridge\LaravelAiPrismTool;
use OpenCompany\PrismRelay\Bridge\PrismResponseMapper;
use Prism\Prism\Enums\StreamEventType as PrismStreamEventType;
use Prism\Prism\Streaming\Events\ProviderToolEvent as ProviderToolStreamEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;

class PrismStreamEventMapper
{
    public static function toLaravel(string $invocationId, StreamEvent $event, string $provider, string $model): ?LaravelStreamEvent
    {
        if (isset($event->id)) {
            $id = strtolower($event->id);
        }

        return tap(match ($event->type()) {
            PrismStreamEventType::StreamStart => new StreamStart($id ?? $event->id, $provider, $model, $event->timestamp, $event->metadata),
            PrismStreamEventType::TextStart => new TextStart($id ?? $event->id, strtolower($event->messageId), $event->timestamp),
            PrismStreamEventType::TextDelta => new TextDelta($id ?? $event->id, strtolower($event->messageId), $event->delta, $event->timestamp),
            PrismStreamEventType::TextComplete => new TextEnd($id ?? $event->id, strtolower($event->messageId), $event->timestamp),
            PrismStreamEventType::ThinkingStart => new ReasoningStart($id ?? $event->id, strtolower($event->reasoningId), $event->timestamp),
            PrismStreamEventType::ThinkingDelta => new ReasoningDelta($id ?? $event->id, strtolower($event->reasoningId), $event->delta, $event->timestamp, $event->summary),
            PrismStreamEventType::ThinkingComplete => new ReasoningEnd($id ?? $event->id, strtolower($event->reasoningId), $event->timestamp, $event->summary ?? null),
            PrismStreamEventType::ToolCall => static::toolCall($event),
            PrismStreamEventType::ToolResult => static::toolResult($event),
            PrismStreamEventType::ProviderToolEvent => static::providerTool($event),
            PrismStreamEventType::StreamEnd => static::streamEnd($event),
            PrismStreamEventType::Error => new Error($event->id, $event->type, $event->message, $event->recoverable, $event->timestamp, $event->metadata),
            default => null,
        }, function ($mapped) use ($invocationId) {
            $mapped?->withInvocationId($invocationId);
        });
    }

    protected static function toolCall(ToolCallEvent $event): ToolCall
    {
        return new ToolCall(
            strtolower($event->id),
            LaravelAiPrismTool::toLaravelToolCall($event->toolCall),
            $event->timestamp,
        );
    }

    protected static function toolResult(ToolResultEvent $event): ToolResult
    {
        return new ToolResult(
            strtolower($event->id),
            LaravelAiPrismTool::toLaravelToolResult($event->toolResult),
            $event->success,
            $event->error,
            $event->timestamp,
        );
    }

    protected static function providerTool(ProviderToolStreamEvent $event): LaravelProviderToolEvent
    {
        return new LaravelProviderToolEvent(
            strtolower($event->id),
            $event->itemId,
            $event->toolType,
            $event->data,
            $event->status,
            $event->timestamp,
        );
    }

    protected static function streamEnd(StreamEndEvent $event): StreamEnd
    {
        return new StreamEnd(
            strtolower($event->id),
            $event->finishReason->value,
            PrismResponseMapper::usage($event->usage),
            $event->timestamp,
        );
    }
}

