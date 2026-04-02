<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Providers;

use Generator;
use Prism\Prism\Embeddings\Request as EmbeddingsRequest;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

class ModelRouter extends Provider
{
    /**
     * @param  array<int, array{match: array<int, string>, provider: Provider}>  $routes
     */
    public function __construct(
        private readonly array $routes,
        private readonly Provider $fallback,
    ) {}

    #[\Override]
    public function text(TextRequest $request): TextResponse
    {
        return $this->match($request->model())->text($request);
    }

    #[\Override]
    public function structured(StructuredRequest $request): StructuredResponse
    {
        return $this->match($request->model())->structured($request);
    }

    #[\Override]
    public function embeddings(EmbeddingsRequest $request): EmbeddingsResponse
    {
        return $this->match($request->model())->embeddings($request);
    }

    #[\Override]
    public function stream(TextRequest $request): Generator
    {
        return $this->match($request->model())->stream($request);
    }

    private function match(string $model): Provider
    {
        foreach ($this->routes as $route) {
            foreach ($route['match'] as $pattern) {
                if (str_starts_with($model, $pattern)) {
                    return $route['provider'];
                }
            }
        }

        return $this->fallback;
    }
}
