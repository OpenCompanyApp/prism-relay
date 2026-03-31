<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Contracts;

use OpenCompany\PrismRelay\Errors\ProviderError;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

interface RelayListener
{
    /**
     * Called before a request is sent to a provider.
     */
    public function beforeRequest(string $provider, string $model): void;

    /**
     * Called after a successful response is received.
     */
    public function afterResponse(string $provider, string $model, Usage $usage, Meta $meta): void;

    /**
     * Called when a provider error is normalized.
     */
    public function onError(ProviderError $error): void;
}
