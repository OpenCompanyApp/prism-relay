<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Support;

use Prism\Prism\Contracts\Message;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;

final class OpenAiCompatibleMessageMapper
{
    /**
     * @param  Message[]  $messages
     * @return array<int, array<string, mixed>>
     */
    public function map(string $provider, array $messages): array
    {
        $mapped = [];

        foreach ($messages as $message) {
            match ($message::class) {
                SystemMessage::class => $mapped[] = $this->mapSystemMessage($provider, $message),
                UserMessage::class => $mapped[] = $this->mapUserMessage($provider, $message),
                AssistantMessage::class => $this->mapAssistantMessage($provider, $message, $mapped),
                ToolResultMessage::class => $this->mapToolResultMessage($provider, $message, $mapped),
                default => throw new \InvalidArgumentException('Unsupported message type: '.$message::class),
            };
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSystemMessage(string $provider, SystemMessage $message): array
    {
        if ($provider === 'openrouter' && $message->providerOptions('cacheType') !== null) {
            return [
                'role' => 'system',
                'content' => [[
                    'type' => 'text',
                    'text' => $message->content,
                    'cache_control' => ['type' => $message->providerOptions('cacheType')],
                ]],
            ];
        }

        return [
            'role' => 'system',
            'content' => $message->content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUserMessage(string $provider, UserMessage $message): array
    {
        if ($provider === 'openrouter' && $message->providerOptions('cacheType') !== null && $message->images() === []) {
            return [
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => $message->text(),
                    'cache_control' => ['type' => $message->providerOptions('cacheType')],
                ]],
            ];
        }

        if ($message->images() === []) {
            return [
                'role' => 'user',
                'content' => $message->text(),
            ];
        }

        return [
            'role' => 'user',
            'content' => $this->mapUserContentParts($provider, $message),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapped
     */
    private function mapAssistantMessage(string $provider, AssistantMessage $message, array &$mapped): void
    {
        $entry = [
            'role' => 'assistant',
            'content' => $message->content,
        ];

        if ($provider === 'openrouter' && $message->providerOptions('cacheType') !== null && $message->content !== '') {
            $entry['content'] = [[
                'type' => 'text',
                'text' => $message->content,
                'cache_control' => ['type' => $message->providerOptions('cacheType')],
            ]];
        }

        if ($message->toolCalls !== []) {
            $entry['tool_calls'] = array_map(fn (ToolCall $toolCall): array => [
                'id' => $toolCall->id,
                'type' => 'function',
                'function' => [
                    'name' => $toolCall->name,
                    'arguments' => is_string($toolCall->arguments) ? $toolCall->arguments : json_encode($toolCall->arguments, JSON_THROW_ON_ERROR),
                ],
            ], $message->toolCalls);
        }

        $mapped[] = $entry;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapped
     */
    private function mapToolResultMessage(string $provider, ToolResultMessage $message, array &$mapped): void
    {
        if ($provider === 'openrouter' && $message->providerOptions('cacheType') !== null) {
            $content = [];
            $totalResults = count($message->toolResults);

            foreach ($message->toolResults as $index => $result) {
                $content[] = $this->mapOpenRouterToolResult($message, $result, $index === $totalResults - 1);
            }

            $mapped[] = [
                'role' => 'tool',
                'content' => $content,
            ];

            return;
        }

        foreach ($message->toolResults as $result) {
            $mapped[] = [
                'role' => 'tool',
                'tool_call_id' => $result->toolCallId,
                'content' => is_string($result->result) ? $result->result : json_encode($result->result, JSON_THROW_ON_ERROR),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOpenRouterToolResult(ToolResultMessage $message, ToolResult $result, bool $isLastResult): array
    {
        return array_filter([
            'type' => 'tool_result',
            'tool_call_id' => $result->toolCallId,
            'content' => is_string($result->result) ? $result->result : json_encode($result->result, JSON_THROW_ON_ERROR),
            'cache_control' => $isLastResult
                ? ['type' => $message->providerOptions('cacheType')]
                : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapUserContentParts(string $provider, UserMessage $message): array
    {
        $parts = [];
        $text = $message->text();

        if ($text !== '') {
            $textPart = [
                'type' => 'text',
                'text' => $text,
            ];

            if ($provider === 'openrouter' && $message->providerOptions('cacheType') !== null) {
                $textPart['cache_control'] = ['type' => $message->providerOptions('cacheType')];
            }

            $parts[] = $textPart;
        }

        foreach ($message->images() as $image) {
            $parts[] = $this->mapImage($image);
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapImage(Image $image): array
    {
        if ($image->isFileId()) {
            return [
                'type' => 'image_file',
                'file_id' => $image->fileId(),
            ];
        }

        $url = $image->isUrl()
            ? $image->url()
            : sprintf(
                'data:%s;base64,%s',
                $image->mimeType() ?? 'image/png',
                $image->base64()
            );

        return [
            'type' => 'image_url',
            'image_url' => ['url' => $url],
        ];
    }
}
