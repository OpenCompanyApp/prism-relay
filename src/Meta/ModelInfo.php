<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Meta;

use OpenCompany\PrismRelay\Caching\CacheCapability;

readonly class ModelInfo
{
    public function __construct(
        public string $model,
        public int $contextWindow,
        public int $maxOutput = 4096,
        public ?float $inputPricePerMillion = null,
        public ?float $outputPricePerMillion = null,
        public ?float $cachedInputPricePerMillion = null,
        public ?float $cachedWritePricePerMillion = null,
        public bool $thinking = false,
        public ?string $displayName = null,
        public string $pricingKind = 'paid',
        public ?float $referenceInputPricePerMillion = null,
        public ?float $referenceOutputPricePerMillion = null,
        public ?string $status = null,
        /** @var list<string> */
        public array $inputModalities = ['text'],
        /** @var list<string> */
        public array $outputModalities = ['text'],
        public CacheCapability $cacheCapability = CacheCapability::None,
    ) {}
}
