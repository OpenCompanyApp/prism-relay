<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use JsonSerializable;
use InvalidArgumentException;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\LocalDocument;
use Laravel\Ai\Files\LocalImage;
use Laravel\Ai\Files\ProviderDocument;
use Laravel\Ai\Files\ProviderImage;
use Laravel\Ai\Files\RemoteDocument;
use Laravel\Ai\Files\RemoteImage;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall as LaravelToolCall;
use Laravel\Ai\Responses\Data\ToolResult as LaravelToolResult;
use Prism\Prism\ValueObjects\Media\Audio as PrismAudio;
use Prism\Prism\ValueObjects\Media\Document as PrismDocument;
use Prism\Prism\ValueObjects\Media\Image as PrismImage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage as PrismAssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage as PrismToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage as PrismUserMessage;
use Prism\Prism\ValueObjects\ToolCall as PrismToolCall;
use Prism\Prism\ValueObjects\ToolResult as PrismToolResult;

class ToolAwarePrismMessages
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

    /**
     * Marshal Prism messages back into Laravel AI messages.
     */
    public static function toLaravelMessages(Collection $messages): Collection
    {
        return $messages->map(function ($message) {
            if ($message instanceof PrismUserMessage) {
                return new \Laravel\Ai\Messages\UserMessage($message->content);
            }

            if ($message instanceof PrismAssistantMessage) {
                return new AssistantMessage(
                    $message->content ?? '',
                    toolCalls: (new Collection($message->toolCalls ?? []))
                        ->map(LaravelAiPrismTool::toLaravelToolCall(...))
                );
            }

            if ($message instanceof PrismToolResultMessage) {
                return new ToolResultMessage(
                    (new Collection($message->toolResults))
                        ->map(LaravelAiPrismTool::toLaravelToolResult(...))
                );
            }

            return $message;
        })->values();
    }

    protected static function fromLaravelAttachments(Collection $attachments): Collection
    {
        return $attachments->map(function ($attachment) {
            if (! $attachment instanceof File && ! $attachment instanceof UploadedFile) {
                throw new InvalidArgumentException(
                    'Unsupported attachment type ['.$attachment::class.']'
                );
            }

            $prismAttachment = match (true) {
                $attachment instanceof ProviderImage => PrismImage::fromFileId($attachment->id),
                $attachment instanceof Base64Image => PrismImage::fromBase64($attachment->base64, $attachment->mime),
                $attachment instanceof LocalImage => PrismImage::fromLocalPath($attachment->path, $attachment->mime),
                $attachment instanceof RemoteImage => PrismImage::fromUrl($attachment->url),
                $attachment instanceof StoredImage => PrismImage::fromStoragePath($attachment->path, $attachment->disk),
                $attachment instanceof ProviderDocument => PrismDocument::fromFileId($attachment->id),
                $attachment instanceof Base64Document => PrismDocument::fromBase64($attachment->base64, $attachment->mime),
                $attachment instanceof LocalDocument => PrismDocument::fromPath($attachment->path),
                $attachment instanceof RemoteDocument => PrismDocument::fromUrl($attachment->url),
                $attachment instanceof StoredDocument => PrismDocument::fromStoragePath($attachment->path, $attachment->disk),
                $attachment instanceof UploadedFile && static::isImage($attachment) => PrismImage::fromBase64(base64_encode($attachment->get()), $attachment->getClientMimeType()),
                $attachment instanceof UploadedFile && static::isAudio($attachment) => PrismAudio::fromBase64(base64_encode($attachment->get()), $attachment->getClientMimeType()),
                $attachment instanceof UploadedFile => PrismDocument::fromBase64(base64_encode($attachment->get()), $attachment->getClientMimeType()),
            };

            if ($attachment instanceof File && $attachment->name) {
                $prismAttachment->as($attachment->name);
            }

            return $prismAttachment;
        });
    }

    protected static function isAudio(UploadedFile $attachment): bool
    {
        return in_array($attachment->getClientMimeType(), [
            'audio/mpeg',
            'audio/wav',
            'audio/x-wav',
            'audio/aac',
            'audio/opus',
        ], true);
    }

    protected static function isImage(UploadedFile $attachment): bool
    {
        return in_array($attachment->getClientMimeType(), [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ], true);
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
