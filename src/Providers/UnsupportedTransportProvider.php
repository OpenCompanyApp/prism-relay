<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Providers;

use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

class UnsupportedTransportProvider extends Provider
{
    public function __construct(
        private readonly string $provider,
        private readonly string $transport,
    ) {}

    #[\Override]
    public function text(TextRequest $request): TextResponse
    {
        throw $this->exception();
    }

    #[\Override]
    public function structured(StructuredRequest $request): StructuredResponse
    {
        throw $this->exception();
    }

    private function exception(): PrismException
    {
        return new PrismException(sprintf(
            'Provider [%s] uses unsupported transport [%s] for Prism runtime execution.',
            $this->provider,
            $this->transport,
        ));
    }
}
