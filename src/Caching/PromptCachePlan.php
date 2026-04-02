<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Caching;

use Prism\Prism\Contracts\Message;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

readonly class PromptCachePlan
{
    /**
     * @param  SystemMessage[]  $systemPrompts
     * @param  Message[]  $messages
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        public array $systemPrompts,
        public array $messages,
        public array $providerOptions = [],
    ) {}
}
