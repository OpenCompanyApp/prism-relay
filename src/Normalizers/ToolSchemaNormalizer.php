<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Normalizers;

class ToolSchemaNormalizer
{
    /**
     * Normalize a tool parameter schema for strict providers.
     *
     * Fixes bare arrays without 'items', adds additionalProperties:false
     * for object schemas, and recursively processes nested structures.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public static function normalize(array $parameters): array
    {
        return self::walkProperties($parameters);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function walkProperties(array $schema): array
    {
        $type = $schema['type'] ?? '';

        // Fix: bare arrays without 'items' definition
        if ($type === 'array' && ! isset($schema['items'])) {
            $schema['items'] = ['type' => 'string'];
        }

        // Fix: add additionalProperties:false for strict mode
        if ($type === 'object' && ! isset($schema['additionalProperties'])) {
            $schema['additionalProperties'] = false;
        }

        // Recurse into nested properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $prop) {
                if (is_array($prop)) {
                    $schema['properties'][$key] = self::walkProperties($prop);
                }
            }
        }

        // Recurse into array items
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::walkProperties($schema['items']);
        }

        // Recurse into anyOf/oneOf/allOf
        foreach (['anyOf', 'oneOf', 'allOf'] as $combiner) {
            if (isset($schema[$combiner]) && is_array($schema[$combiner])) {
                $schema[$combiner] = array_map(
                    fn (array $sub) => self::walkProperties($sub),
                    $schema[$combiner],
                );
            }
        }

        return $schema;
    }
}
