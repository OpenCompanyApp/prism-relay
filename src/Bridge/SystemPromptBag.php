<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge;

use Prism\Prism\ValueObjects\Messages\SystemMessage;

/**
 * Request-scoped container for split system prompts.
 *
 * Set this in the container before calling $agent->prompt() so the
 * CachingPrismGateway can apply cache-aware prompt splitting without
 * any modifications to laravel/ai's interface or traits.
 */
final readonly class SystemPromptBag
{
    /**
     * @param  string[]  $prompts  Ordered system prompts (e.g. [stable, volatile])
     */
    public function __construct(
        public array $prompts,
    ) {}

    public function hasPrompts(): bool
    {
        return $this->prompts !== [];
    }

    /**
     * @return SystemMessage[]
     */
    public function toPrismSystemMessages(): array
    {
        return array_values(array_map(
            fn (string $prompt) => new SystemMessage($prompt),
            array_filter($this->prompts, fn (string $prompt) => trim($prompt) !== ''),
        ));
    }
}
