<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use Laravel\Ai\Gateway\Prism\PrismMessages;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall as LaravelToolCall;
use Laravel\Ai\Responses\Data\ToolResult as LaravelToolResult;
use Prism\Prism\ValueObjects\Messages\AssistantMessage as PrismAssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage as PrismToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage as PrismUserMessage;
use Prism\Prism\ValueObjects\ToolCall as PrismToolCall;
use Prism\Prism\ValueObjects\ToolResult as PrismToolResult;

class ToolAwarePrismMessages extends PrismMessages
{
    /**
     * Marshal Laravel AI messages into Prism messages without dropping tool history.
     */
    public static function fromLaravelMessages(Collection $messages): Collection
    {
        return $messages
            ->map(function ($message) {
                $message = Message::tryFrom($message);

                if ($message->role === MessageRole::User) {
                    return new PrismUserMessage(
                        $message->content,
                        additionalContent: static::fromLaravelAttachments($message->attachments ?? new Collection)->all(),
                    );
                }

                if ($message->role === MessageRole::Assistant && $message instanceof AssistantMessage) {
                    return new PrismAssistantMessage(
                        $message->content ?? '',
                        toolCalls: $message->toolCalls
                            ->map(fn (LaravelToolCall $toolCall) => new PrismToolCall(
                                id: $toolCall->id,
                                name: $toolCall->name,
                                arguments: $toolCall->arguments,
                                resultId: $toolCall->resultId,
                                reasoningId: $toolCall->reasoningId,
                                reasoningSummary: $toolCall->reasoningSummary,
                            ))
                            ->all(),
                    );
                }

                if ($message->role === MessageRole::ToolResult && $message instanceof ToolResultMessage) {
                    return new PrismToolResultMessage(
                        $message->toolResults
                            ->map(fn (LaravelToolResult $toolResult) => new PrismToolResult(
                                toolCallId: $toolResult->id,
                                toolName: $toolResult->name,
                                args: $toolResult->arguments,
                                result: static::normalizeToolResult($toolResult->result),
                                toolCallResultId: $toolResult->resultId,
                            ))
                            ->all(),
                    );
                }

                if ($message->role === MessageRole::Assistant) {
                    return new PrismAssistantMessage($message->content ?? '');
                }

                return null;
            })->filter()->values();
    }

    private static function normalizeToolResult(mixed $result): int|float|string|array|null
    {
        if (is_int($result) || is_float($result) || is_string($result) || is_array($result) || $result === null) {
            return $result;
        }

        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }

        if ($result instanceof Arrayable) {
            return $result->toArray();
        }

        if ($result instanceof JsonSerializable) {
            $serialized = $result->jsonSerialize();

            if (is_int($serialized) || is_float($serialized) || is_string($serialized) || is_array($serialized) || $serialized === null) {
                return $serialized;
            }

            if (is_bool($serialized)) {
                return $serialized ? 'true' : 'false';
            }
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: (string) $result;
    }
}
