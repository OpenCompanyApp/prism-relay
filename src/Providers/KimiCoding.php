<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Providers;

use Prism\Prism\Providers\DeepSeek\DeepSeek;

class KimiCoding
{
    public const URL = 'https://api.moonshot.ai/v1';

    public const DEFAULT_MODEL = 'kimi-k2.5';

    public static function create(string $apiKey, ?string $url = null): DeepSeek
    {
        return new DeepSeek(
            apiKey: $apiKey,
            url: $url ?? self::URL,
        );
    }
}
