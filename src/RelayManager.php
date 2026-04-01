<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay;

use OpenCompany\PrismRelay\Providers\Glm;
use OpenCompany\PrismRelay\Providers\GlmCoding;
use OpenCompany\PrismRelay\Providers\Kimi;
use OpenCompany\PrismRelay\Providers\KimiCoding;
use OpenCompany\PrismRelay\Providers\MiniMax;
use OpenCompany\PrismRelay\Providers\MiniMaxCn;
use Prism\Prism\PrismManager;

class RelayManager
{
    /** @var array<string, class-string> */
    private array $providerMap = [
        'z-api' => Glm::class,
        'z' => GlmCoding::class,
        'kimi' => Kimi::class,
        'kimi-coding' => KimiCoding::class,
        'minimax' => MiniMax::class,
        'minimax-cn' => MiniMaxCn::class,
    ];

    /**
     * Register all relay providers with a PrismManager instance.
     */
    public function register(PrismManager $manager): void
    {
        foreach ($this->providerMap as $name => $factoryClass) {
            $manager->extend($name, function ($app, array $config) use ($factoryClass) {
                return $factoryClass::create(
                    apiKey: $config['api_key'] ?? '',
                    url: $config['url'] ?? null,
                );
            });
        }
    }

    /**
     * Get list of relay-registered provider names.
     *
     * @return string[]
     */
    public function providers(): array
    {
        return array_keys($this->providerMap);
    }

    /**
     * Check if a provider name is managed by relay.
     */
    public function isRelayProvider(string $name): bool
    {
        return isset($this->providerMap[$name]);
    }
}
