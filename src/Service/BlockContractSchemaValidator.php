<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * Strict validation for CMS blocks based on the JSON schema used by the block editor.
 *
 * Goal: fail fast on the API level (422) with useful error details,
 * matching the editor behaviour (unexpected fields, wrong types, missing required fields).
 */
final class BlockContractSchemaValidator
{
    /** @var array<string,mixed> */
    private array $schema;

    /** @var array<string, list<string>> */
    private array $variantsByType;

    /** @var array<string, array<string,mixed>> */
    private array $dataSchemaByType;

    public function __construct(?string $schemaPath = null)
    {
        $schemaFile = $schemaPath ?? dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $json = @file_get_contents($schemaFile);
        if ($json === false) {
            throw new RuntimeException(sprintf('Block contract schema not readable at %s', $schemaFile));
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Block contract schema is invalid JSON');
        }

        $this->schema = $decoded;
        $this->variantsByType = $this->extractVariantsByType($decoded);
        $this->dataSchemaByType = $this->extractDataSchemasByType($decoded);
    }

    /**
     * @param array<string,mixed> $pageContent
     * @return list<array{blockId:string,path:string,message:string}>
     */
    public function validatePageContent(array $pageContent): array
    {
        $blocks = $pageContent['blocks'] ?? null;
        if (!is_array($blocks)) {
            return [[
                'blockId' => '(page)',
                'path' => '/blocks',
                'message' => 'Blocks must be an array',
            ]];
        }

        $errors = [];
        foreach ($blocks as $idx => $block) {
            if (!is_array($block)) {
                $errors[] = [
                    'blockId' => sprintf('#%d', $idx),
                    'path' => sprintf('/blocks/%d', $idx),
                    'message' => 'Block must be an object',
                ];
                continue;
            }

            $blockId = isset($block['id']) && is_string($block['id']) ? $block['id'] : sprintf('#%d', $idx);
            $type = isset($block['type']) && is_string($block['type']) ? $block['type'] : '';
            $variant = isset($block['variant']) && is_string($block['variant']) ? $block['variant'] : '';

            if ($type === '' || !isset($this->variantsByType[$type])) {
                $errors[] = [
                    'blockId' => $blockId,
                    'path' => sprintf('/blocks/%d/type', $idx),
                    'message' => sprintf('Unknown block type: %s', $type !== '' ? $type : '(missing)'),
                ];
                continue;
            }

            if ($variant === '' || !in_array($variant, $this->variantsByType[$type], true)) {
                $errors[] = [
                    'blockId' => $blockId,
                    'path' => sprintf('/blocks/%d/variant', $idx),
                    'message' => sprintf('Invalid variant for %s: %s', $type, $variant !== '' ? $variant : '(missing)'),
                ];
            }

            $data = $block['data'] ?? null;
            if (!is_array($data)) {
                $errors[] = [
                    'blockId' => $blockId,
                    'path' => sprintf('/blocks/%d/data', $idx),
                    'message' => 'Expected object',
                ];
                continue;
            }

            if (isset($this->dataSchemaByType[$type])) {
                $schema = $this->dataSchemaByType[$type];
                $errors = array_merge(
                    $errors,
                    $this->validateAgainstSchema($data, $schema, $blockId, sprintf('/blocks/%d/data', $idx))
                );
            }
        }

        return $errors;
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $schema
     * @return list<array{blockId:string,path:string,message:string}>
     */
    private function validateAgainstSchema($value, array $schema, string $blockId, string $path): array
    {
        // Resolve $ref
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $resolved = $this->resolveRef($schema['$ref']);
            return $this->validateAgainstSchema($value, $resolved, $blockId, $path);
        }

        $type = $schema['type'] ?? null;
        if ($type === 'object') {
            if (!is_array($value)) {
                return [[
                    'blockId' => $blockId,
                    'path' => $path,
                    'message' => 'Expected object',
                ]];
            }

            $errors = [];

            $required = $schema['required'] ?? [];
            if (is_array($required)) {
                foreach ($required as $req) {
                    if (!is_string($req)) {
                        continue;
                    }
                    if (!array_key_exists($req, $value)) {
                        $errors[] = [
                            'blockId' => $blockId,
                            'path' => $path . '/' . $req,
                            'message' => 'Missing required field',
                        ];
                    }
                }
            }

            $properties = $schema['properties'] ?? [];
            $allowAdditional = (bool)($schema['additionalProperties'] ?? false);

            if (is_array($properties)) {
                foreach ($value as $k => $v) {
                    if (!is_string($k)) {
                        continue;
                    }
                    if (!array_key_exists($k, $properties)) {
                        if (!$allowAdditional) {
                            $errors[] = [
                                'blockId' => $blockId,
                                'path' => $path . '/' . $k,
                                'message' => 'Unexpected field',
                            ];
                        }
                        continue;
                    }

                    $propSchema = $properties[$k];
                    if (is_array($propSchema)) {
                        $errors = array_merge(
                            $errors,
                            $this->validateAgainstSchema($v, $propSchema, $blockId, $path . '/' . $k)
                        );
                    }
                }

                // Validate known properties that are present (covers type checks even when additionalProperties=true)
                foreach ($properties as $k => $propSchema) {
                    if (!is_string($k) || !array_key_exists($k, $value)) {
                        continue;
                    }
                    if (!is_array($propSchema)) {
                        continue;
                    }
                    $errors = array_merge(
                        $errors,
                        $this->validateAgainstSchema($value[$k], $propSchema, $blockId, $path . '/' . $k)
                    );
                }
            }

            return $errors;
        }

        if ($type === 'array') {
            if (!is_array($value)) {
                return [[
                    'blockId' => $blockId,
                    'path' => $path,
                    'message' => 'Expected array',
                ]];
            }

            $errors = [];
            $itemSchema = $schema['items'] ?? null;
            if (is_array($itemSchema)) {
                foreach ($value as $i => $item) {
                    $errors = array_merge(
                        $errors,
                        $this->validateAgainstSchema($item, $itemSchema, $blockId, $path . '/' . (string) $i)
                    );
                }
            }

            return $errors;
        }

        if ($type === 'string') {
            if (!is_string($value)) {
                return [[
                    'blockId' => $blockId,
                    'path' => $path,
                    'message' => 'Expected string',
                ]];
            }
            return [];
        }

        if ($type === 'boolean') {
            if (!is_bool($value)) {
                return [[
                    'blockId' => $blockId,
                    'path' => $path,
                    'message' => 'Expected boolean',
                ]];
            }
            return [];
        }

        if ($type === 'number') {
            if (!is_int($value) && !is_float($value)) {
                return [[
                    'blockId' => $blockId,
                    'path' => $path,
                    'message' => 'Expected number',
                ]];
            }
            return [];
        }

        if ($type === 'integer') {
            if (!is_int($value)) {
                return [[
                    'blockId' => $blockId,
                    'path' => $path,
                    'message' => 'Expected integer',
                ]];
            }
            return [];
        }

        // Fallback: handle enums
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            if (!in_array($value, $schema['enum'], true)) {
                return [[
                    'blockId' => $blockId,
                    'path' => $path,
                    'message' => 'Value not in enum',
                ]];
            }
        }

        return [];
    }

    /** @return array<string, list<string>> */
    private function extractVariantsByType(array $schema): array
    {
        $variants = [];
        foreach (($schema['oneOf'] ?? []) as $definition) {
            $type = null;
            $variantEnum = null;

            if (is_array($definition) && isset($definition['properties']) && is_array($definition['properties'])) {
                $type = $definition['properties']['type']['const'] ?? null;
                $variantEnum = $definition['properties']['variant']['enum'] ?? null;
            }

            if ($type === null && isset($definition['allOf']) && is_array($definition['allOf'])) {
                foreach ($definition['allOf'] as $subSchema) {
                    if (
                        !is_array($subSchema)
                        || !isset($subSchema['properties'])
                        || !is_array($subSchema['properties'])
                    ) {
                        continue;
                    }
                    $type ??= $subSchema['properties']['type']['const'] ?? null;
                    $variantEnum ??= $subSchema['properties']['variant']['enum'] ?? null;
                }
            }

            if (is_string($type) && is_array($variantEnum)) {
                $variants[$type] = array_values(array_filter(array_map('strval', $variantEnum)));
            }
        }

        return $variants;
    }

    /** @return array<string, array<string,mixed>> */
    private function extractDataSchemasByType(array $schema): array
    {
        $dataSchemas = [];
        foreach (($schema['oneOf'] ?? []) as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $type = null;
            $dataSchema = null;

            if (isset($definition['properties']) && is_array($definition['properties'])) {
                $type = $definition['properties']['type']['const'] ?? null;
                $dataSchema = $definition['properties']['data'] ?? null;
            }

            if ($type === null && isset($definition['allOf']) && is_array($definition['allOf'])) {
                foreach ($definition['allOf'] as $subSchema) {
                    if (
                        !is_array($subSchema)
                        || !isset($subSchema['properties'])
                        || !is_array($subSchema['properties'])
                    ) {
                        continue;
                    }
                    $type ??= $subSchema['properties']['type']['const'] ?? null;
                    $dataSchema ??= $subSchema['properties']['data'] ?? null;
                }
            }

            if (is_string($type) && is_array($dataSchema)) {
                $dataSchemas[$type] = $dataSchema;
            }
        }

        return $dataSchemas;
    }

    /** @return array<string,mixed> */
    private function resolveRef(string $ref): array
    {
        if (!str_starts_with($ref, '#/')) {
            throw new RuntimeException(sprintf('Unsupported schema ref: %s', $ref));
        }

        $node = $this->schema;
        $path = explode('/', substr($ref, 2));
        foreach ($path as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                throw new RuntimeException(sprintf('Schema ref not found: %s', $ref));
            }
            $node = $node[$segment];
        }

        if (!is_array($node)) {
            throw new RuntimeException(sprintf('Schema ref invalid: %s', $ref));
        }

        return $node;
    }
}
