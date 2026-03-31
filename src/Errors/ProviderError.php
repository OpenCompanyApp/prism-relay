<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Errors;

class ProviderError extends \RuntimeException
{
    /**
     * @param  \Prism\Prism\ValueObjects\ProviderRateLimit[]  $rateLimits
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly ErrorCategory $category,
        public readonly bool $retryable,
        public readonly ?int $retryAfterSeconds = null,
        public readonly array $rateLimits = [],
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
