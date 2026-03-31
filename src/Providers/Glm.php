<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Providers;

use Prism\Prism\Providers\DeepSeek\DeepSeek;

class Glm
{
    public const URL = 'https://open.bigmodel.cn/api/paas/v4';

    public const DEFAULT_MODEL = 'glm-4-plus';

    public static function create(string $apiKey, ?string $url = null): DeepSeek
    {
        return new DeepSeek(
            apiKey: $apiKey,
            url: $url ?? self::URL,
        );
    }
}
