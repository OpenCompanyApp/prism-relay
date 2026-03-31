<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Normalizers;

use Illuminate\Http\Client\ConnectionException;
use OpenCompany\PrismRelay\Errors\ErrorCategory;
use OpenCompany\PrismRelay\Errors\ProviderError;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;

class ErrorNormalizer
{
    /**
     * Categorize a Prism/HTTP exception into a structured ProviderError.
     */
    public static function normalize(\Throwable $e, string $provider, string $model): ProviderError
    {
        return match (true) {
            $e instanceof PrismRateLimitedException => new ProviderError(
                provider: $provider,
                model: $model,
                category: ErrorCategory::RateLimit,
                retryable: true,
                retryAfterSeconds: $e->retryAfter,
                rateLimits: $e->rateLimits,
                message: $e->getMessage(),
                previous: $e,
            ),
            $e instanceof PrismProviderOverloadedException => new ProviderError(
                provider: $provider,
                model: $model,
                category: ErrorCategory::Overloaded,
                retryable: true,
                retryAfterSeconds: 30,
                message: $e->getMessage(),
                previous: $e,
            ),
            $e instanceof PrismRequestTooLargeException => new ProviderError(
                provider: $provider,
                model: $model,
                category: ErrorCategory::RequestTooLarge,
                retryable: false,
                message: $e->getMessage(),
                previous: $e,
            ),
            $e instanceof PrismException => self::categorizePrismException($e, $provider, $model),
            $e instanceof ConnectionException => new ProviderError(
                provider: $provider,
                model: $model,
                category: ErrorCategory::Network,
                retryable: true,
                message: $e->getMessage(),
                previous: $e,
            ),
            self::hasRetryAfter($e) => new ProviderError(
                provider: $provider,
                model: $model,
                category: ErrorCategory::Server,
                retryable: true,
                retryAfterSeconds: self::extractRetryAfter($e),
                message: $e->getMessage(),
                previous: $e,
            ),
            default => new ProviderError(
                provider: $provider,
                model: $model,
                category: self::categorizeGeneric($e),
                retryable: self::isRetryable($e),
                message: $e->getMessage(),
                previous: $e,
            ),
        };
    }

    private static function categorizePrismException(PrismException $e, string $provider, string $model): ProviderError
    {
        $message = strtolower($e->getMessage());

        $category = match (true) {
            str_contains($message, 'unauthorized') || str_contains($message, '401') || str_contains($message, 'invalid.*key') => ErrorCategory::Auth,
            str_contains($message, 'content') && (str_contains($message, 'filter') || str_contains($message, 'safety') || str_contains($message, 'blocked')) => ErrorCategory::ContentFilter,
            str_contains($message, 'balance') || str_contains($message, '402') || str_contains($message, 'insufficient') => ErrorCategory::InsufficientBalance,
            str_contains($message, '400') || str_contains($message, 'invalid') || str_contains($message, 'malformed') => ErrorCategory::InvalidRequest,
            default => ErrorCategory::Server,
        };

        return new ProviderError(
            provider: $provider,
            model: $model,
            category: $category,
            retryable: in_array($category, [ErrorCategory::Server]),
            message: $e->getMessage(),
            previous: $e,
        );
    }

    private static function categorizeGeneric(\Throwable $e): ErrorCategory
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out') || str_contains($message, 'connection')) {
            return ErrorCategory::Network;
        }

        return ErrorCategory::Server;
    }

    private static function isRetryable(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'connection')
            || str_contains($message, '502')
            || str_contains($message, '503')
            || str_contains($message, '504');
    }

    /**
     * Check if an exception carries a retryAfterSeconds hint (duck-typing).
     * Supports KosmoKrator's RetryableHttpException and similar.
     */
    private static function hasRetryAfter(\Throwable $e): bool
    {
        return property_exists($e, 'retryAfterSeconds')
            || method_exists($e, 'getRetryAfterSeconds');
    }

    private static function extractRetryAfter(\Throwable $e): ?int
    {
        if (property_exists($e, 'retryAfterSeconds') && $e->retryAfterSeconds !== null) {
            return (int) $e->retryAfterSeconds;
        }

        if (method_exists($e, 'getRetryAfterSeconds')) {
            $value = $e->getRetryAfterSeconds();

            return $value !== null ? (int) $value : null;
        }

        return null;
    }
}
