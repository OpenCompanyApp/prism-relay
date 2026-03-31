<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Meta;

use OpenCompany\PrismRelay\Caching\CacheCapability;

readonly class ModelInfo
{
    public function __construct(
        public string $model,
        public int $contextWindow,
        public ?float $inputPricePerMillion = null,
        public ?float $outputPricePerMillion = null,
        public ?float $cachedInputPricePerMillion = null,
        public CacheCapability $cacheCapability = CacheCapability::None,
    ) {}
}
