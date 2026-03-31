<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Providers;

use Prism\Prism\Providers\DeepSeek\DeepSeek;

class GlmCoding
{
    public const URL = 'https://api.z.ai/api/coding/paas/v4';

    public const DEFAULT_MODEL = 'glm-4.7';

    public static function create(string $apiKey, ?string $url = null): DeepSeek
    {
        return new DeepSeek(
            apiKey: $apiKey,
            url: $url ?? self::URL,
        );
    }
}
