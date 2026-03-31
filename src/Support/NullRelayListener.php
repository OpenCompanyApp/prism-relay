<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Support;

use OpenCompany\PrismRelay\Contracts\RelayListener;
use OpenCompany\PrismRelay\Errors\ProviderError;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

class NullRelayListener implements RelayListener
{
    public function beforeRequest(string $provider, string $model): void {}

    public function afterResponse(string $provider, string $model, Usage $usage, Meta $meta): void {}

    public function onError(ProviderError $error): void {}
}
