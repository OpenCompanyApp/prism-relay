<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Caching;

enum CacheCapability: string
{
    /** Provider does not support prompt caching */
    case None = 'none';

    /** Provider caches automatically, no explicit config needed (OpenAI) */
    case Auto = 'auto';

    /** Provider supports explicit cache control via provider options (Anthropic, OpenRouter) */
    case Ephemeral = 'ephemeral';

    /** Provider has a dedicated caching API (Gemini) */
    case Dedicated = 'dedicated';
}
