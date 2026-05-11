<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\Citation;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\StructuredStep;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Responses\Data\Usage;
use Prism\Prism\Enums\Citations\CitationSourceType;
use Prism\Prism\Enums\FinishReason as PrismFinishReason;
use Prism\Prism\Structured\Step as PrismStructuredStep;
use Prism\Prism\Text\Step as PrismTextStep;
use Prism\Prism\ValueObjects\Citation as PrismCitation;
use Prism\Prism\ValueObjects\MessagePartWithCitations;
use Prism\Prism\ValueObjects\Usage as PrismUsageValueObject;

class PrismResponseMapper
{
    public static function usage(?PrismUsageValueObject $usage): Usage
    {
        return new Usage(
            $usage?->promptTokens ?: 0,
            $usage?->completionTokens ?: 0,
            $usage?->cacheWriteInputTokens ?: 0,
            $usage?->cacheReadInputTokens ?: 0,
            $usage?->thoughtTokens ?: 0,
        );
    }

    public static function steps(Collection $steps, string $provider): Collection
    {
        return $steps->map(fn ($step) => match (true) {
            $step instanceof PrismStructuredStep => static::structuredStep($step, $provider),
            $step instanceof PrismTextStep => static::step($step, $provider),
            default => null,
        })->filter()->values();
    }

    public static function citations(Collection $citations): Collection
    {
        return $citations
            ->flatMap(fn (MessagePartWithCitations $part) => $part->citations)
            ->map(static::citation(...))
            ->filter()
            ->unique(fn (Citation $citation) => $citation->title)
            ->values();
    }

    public static function citation(PrismCitation $citation): ?Citation
    {
        if ($citation->sourceType !== CitationSourceType::Url) {
            return null;
        }

        return new UrlCitation($citation->source, $citation->sourceTitle);
    }

    private static function step(PrismTextStep $step, string $provider): Step
    {
        return new Step(
            $step->text,
            (new Collection($step->toolCalls))->map(LaravelAiPrismTool::toLaravelToolCall(...))->all(),
            (new Collection($step->toolResults))->map(LaravelAiPrismTool::toLaravelToolResult(...))->all(),
            static::finishReason($step->finishReason),
            static::usage($step->usage),
            new Meta($provider, $step->meta->model),
        );
    }

    private static function structuredStep(PrismStructuredStep $step, string $provider): StructuredStep
    {
        return new StructuredStep(
            $step->text,
            $step->structured,
            (new Collection($step->toolCalls))->map(LaravelAiPrismTool::toLaravelToolCall(...))->all(),
            (new Collection($step->toolResults))->map(LaravelAiPrismTool::toLaravelToolResult(...))->all(),
            static::finishReason($step->finishReason),
            static::usage($step->usage),
            new Meta($provider, $step->meta->model),
        );
    }

    private static function finishReason(PrismFinishReason $reason): FinishReason
    {
        return match ($reason) {
            PrismFinishReason::Stop => FinishReason::Stop,
            PrismFinishReason::ToolCalls => FinishReason::ToolCalls,
            PrismFinishReason::Length => FinishReason::Length,
            PrismFinishReason::ContentFilter => FinishReason::ContentFilter,
            PrismFinishReason::Error => FinishReason::Error,
            PrismFinishReason::Other, PrismFinishReason::Unknown => FinishReason::Unknown,
        };
    }
}

