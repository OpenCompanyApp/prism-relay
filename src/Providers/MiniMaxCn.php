<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Providers;

use Prism\Prism\Providers\Anthropic\Anthropic;

class MiniMaxCn
{
    public const URL = 'https://api.minimaxi.com/anthropic/v1';

    public const DEFAULT_MODEL = 'MiniMax-M1';

    public static function create(string $apiKey, ?string $url = null): Anthropic
    {
        return new Anthropic(
            apiKey: $apiKey,
            apiVersion: '2023-06-01',
            url: $url ?? self::URL,
        );
    }
}
