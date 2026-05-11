<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge;

use Illuminate\Contracts\Events\Dispatcher;
use OpenCompany\PrismRelay\Bridge\LaravelAi\RelayTextGateway;
use OpenCompany\PrismRelay\Relay;

/**
 * Backward-compatible class name for OpenCompany apps that used the old
 * Laravel AI PrismGateway bridge. Laravel AI 0.6 removed that gateway, so the
 * implementation now delegates to the relay-owned TextGateway adapter.
 */
class CachingPrismGateway extends RelayTextGateway
{
    public function __construct(?Dispatcher $events = null, ?Relay $relay = null)
    {
        parent::__construct(relay: $relay);
    }
}
