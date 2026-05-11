<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Bridge;

use Illuminate\JsonSchema\Types\ObjectType;
use Prism\Prism\Contracts\HasSchemaType;
use Prism\Prism\Contracts\Schema;

final readonly class PrismObjectSchema implements Schema, HasSchemaType
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        private array $schema,
        private string $name = 'schema_definition',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            ...$this->disableAdditionalProperties((new ObjectType($this->schema))->withoutAdditionalProperties()->toArray()),
        ];
    }

    public function schemaType(): string
    {
        return 'object';
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function disableAdditionalProperties(array $schema): array
    {
        $type = $schema['type'] ?? null;

        if ($type === 'object' || (is_array($type) && in_array('object', $type, true))) {
            $schema['additionalProperties'] = false;

            foreach ($schema['properties'] ?? [] as $key => $property) {
                if (is_array($property)) {
                    $schema['properties'][$key] = $this->disableAdditionalProperties($property);
                }
            }
        }

        if (is_array($schema['items'] ?? null)) {
            $schema['items'] = $this->disableAdditionalProperties($schema['items']);
        }

        return $schema;
    }
}

