<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Reasoning;

enum ReasoningCapability: string
{
    /** Provider/model does not support extended reasoning */
    case None = 'none';

    /** Provider accepts a reasoning_effort parameter to control thinking depth (OpenAI, XAI) */
    case Effort = 'effort';

    /** Thinking models on this provider always reason — no explicit param needed (DeepSeek, StepFun) */
    case AlwaysOn = 'always_on';
}
