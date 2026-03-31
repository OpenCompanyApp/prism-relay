<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Normalizers;

use Prism\Prism\ValueObjects\ToolCall;

class ToolCallNormalizer
{
    /**
     * Normalize an array of tool calls, fixing known provider quirks.
     *
     * @param  ToolCall[]  $toolCalls
     * @return ToolCall[]
     */
    public static function normalize(array $toolCalls): array
    {
        return array_map(self::normalizeOne(...), $toolCalls);
    }

    private static function normalizeOne(ToolCall $toolCall): ToolCall
    {
        $name = $toolCall->name;
        $arguments = $toolCall->arguments;
        $id = $toolCall->id;

        // Fix 1: Groq/Llama embeds arguments in function.name (#983)
        // e.g. name="function_name{"arg":"val"}" → extract and merge
        if (str_contains($name, '{')) {
            $bracePos = strpos($name, '{');
            $embedded = substr($name, $bracePos);
            $name = substr($name, 0, $bracePos);

            $decoded = self::safeJsonDecode($embedded);
            if ($decoded !== null) {
                if (is_array($arguments)) {
                    $arguments = array_merge($arguments, $decoded);
                } elseif (is_string($arguments)) {
                    $existingDecoded = self::safeJsonDecode(
                        preg_replace('/[\x00-\x1F\x7F]/', '', $arguments) ?? ''
                    );
                    $arguments = array_merge($existingDecoded ?? [], $decoded);
                } else {
                    $arguments = $decoded;
                }
            }
        }

        // Fix 2: Null/empty tool call IDs (Groq streaming #966)
        if ($id === '' || $id === '0') {
            $id = 'relay_' . bin2hex(random_bytes(8));
        }

        // Fix 3: Sanitize string arguments
        if (is_string($arguments)) {
            // Strip control characters (DeepSeek #936/#937)
            $arguments = preg_replace('/[\x00-\x1F\x7F]/', '', $arguments) ?? '';

            // Handle literal "null" string (#982)
            if ($arguments === 'null' || $arguments === '' || $arguments === '0') {
                $arguments = [];
            } else {
                $decoded = self::safeJsonDecode($arguments);
                $arguments = $decoded ?? [];
            }
        }

        // Fix 4: Coerce string booleans/numbers (Groq/Llama #984)
        if (is_array($arguments)) {
            $arguments = self::coerceTypes($arguments);
        }

        // Check if anything actually changed
        if ($id === $toolCall->id && $name === $toolCall->name && $arguments === $toolCall->arguments) {
            return $toolCall;
        }

        return new ToolCall(
            id: $id,
            name: $name,
            arguments: $arguments,
            resultId: $toolCall->resultId,
            reasoningId: $toolCall->reasoningId,
            reasoningSummary: $toolCall->reasoningSummary,
        );
    }

    /**
     * Recursively coerce string representations to native types.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private static function coerceTypes(array $args): array
    {
        foreach ($args as $key => $value) {
            if (is_string($value)) {
                if ($value === 'true') {
                    $args[$key] = true;
                } elseif ($value === 'false') {
                    $args[$key] = false;
                } elseif ($value === 'null') {
                    $args[$key] = null;
                } elseif (is_numeric($value) && ! str_contains($value, ' ')) {
                    // Only coerce obvious numeric strings (no spaces, not UUIDs, etc.)
                    if (str_contains($value, '.')) {
                        $args[$key] = (float) $value;
                    } elseif (strlen($value) < 16) {
                        // Avoid coercing long numeric strings (could be IDs)
                        $args[$key] = (int) $value;
                    }
                }
            } elseif (is_array($value)) {
                $args[$key] = self::coerceTypes($value);
            }
        }

        return $args;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function safeJsonDecode(string $json): ?array
    {
        if ($json === '' || $json === '0') {
            return null;
        }

        try {
            $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($result) ? $result : null;
        } catch (\JsonException) {
            return null;
        }
    }
}
