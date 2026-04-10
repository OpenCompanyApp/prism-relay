<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Contracts;

interface HasSystemPrompts
{
    /**
     * Get the system prompts for cache-friendly splitting.
     *
     * Typically returns [stable_prompt, volatile_prompt] where:
     * - stable_prompt: identity, instructions, memory (changes rarely — cacheable)
     * - volatile_prompt: current time, context, task (changes every request)
     *
     * @return string[]
     */
    public function systemPrompts(): array;
}
