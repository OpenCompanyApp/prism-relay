<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge;

use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\ToolCall as PrismToolCall;
use Prism\Prism\ValueObjects\ToolResult as PrismToolResult;
use Throwable;
use TypeError;

class LaravelAiPrismTool extends Tool
{
    public function handle(...$args): string
    {
        try {
            $value = call_user_func($this->fn, $args);

            if (! is_string($value)) {
                throw PrismException::invalidReturnTypeInTool($this->name, new TypeError('Return value must be of type string'));
            }

            return $value;
        } catch (Throwable $e) {
            return $this->handleToolException($e, $args);
        }
    }

    public static function toLaravelToolCall(PrismToolCall|array $toolCall): ToolCall
    {
        if (is_array($toolCall)) {
            return new ToolCall(
                $toolCall['id'] ?? '',
                $toolCall['name'] ?? '',
                static::normalizeArguments($toolCall['arguments']['schema_definition'] ?? $toolCall['arguments'] ?? []),
                $toolCall['resultId'] ?? $toolCall['result_id'] ?? null,
                $toolCall['reasoningId'] ?? $toolCall['reasoning_id'] ?? null,
                $toolCall['reasoningSummary'] ?? $toolCall['reasoning_summary'] ?? null,
            );
        }

        $arguments = $toolCall->arguments();

        return new ToolCall(
            $toolCall->id,
            $toolCall->name,
            static::normalizeArguments($arguments['schema_definition'] ?? $arguments),
            $toolCall->resultId,
            $toolCall->reasoningId,
            $toolCall->reasoningSummary,
        );
    }

    public static function toLaravelToolResult(PrismToolResult|array $toolResult): ToolResult
    {
        if (is_array($toolResult)) {
            return new ToolResult(
                $toolResult['toolCallId'] ?? $toolResult['tool_call_id'] ?? '',
                $toolResult['toolName'] ?? $toolResult['tool_name'] ?? '',
                static::normalizeArguments($toolResult['args']['schema_definition'] ?? $toolResult['args'] ?? []),
                $toolResult['result'] ?? '',
                $toolResult['toolCallResultId'] ?? $toolResult['tool_call_result_id'] ?? null,
            );
        }

        return new ToolResult(
            $toolResult->toolCallId,
            $toolResult->toolName,
            static::normalizeArguments($toolResult->args['schema_definition'] ?? $toolResult->args),
            $toolResult->result,
            $toolResult->toolCallResultId,
        );
    }

    protected static function normalizeArguments(mixed $arguments): array
    {
        if (is_string($arguments)) {
            $decoded = json_decode($arguments, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($arguments) ? $arguments : [];
    }
}

