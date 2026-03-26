<?php

declare(strict_types=1);

namespace App\Service\Mcp;

trait McpToolTrait
{
    private string $defaultNamespace;

    private function resolveNamespace(array $args): string
    {
        $ns = isset($args['namespace']) && is_string($args['namespace']) ? trim($args['namespace']) : '';
        return $ns !== '' ? $ns : $this->defaultNamespace;
    }

    private function requireString(array $args, string $key): string
    {
        $value = isset($args[$key]) && is_string($args[$key]) ? trim($args[$key]) : '';
        if ($value === '') {
            throw new \InvalidArgumentException($key . ' is required');
        }
        return $value;
    }

    private function requireInt(array $args, string $key): int
    {
        $value = isset($args[$key]) ? (int) $args[$key] : 0;
        if ($value <= 0) {
            throw new \InvalidArgumentException($key . ' is required and must be a positive integer');
        }
        return $value;
    }

    private function optionalString(array $args, string $key, string $default = ''): string
    {
        return isset($args[$key]) && is_string($args[$key]) ? trim($args[$key]) : $default;
    }

    private function optionalInt(array $args, string $key, int $default = 0): int
    {
        return isset($args[$key]) ? (int) $args[$key] : $default;
    }

    private function optionalBool(array $args, string $key, ?bool $default = null): ?bool
    {
        if (!array_key_exists($key, $args)) {
            return $default;
        }
        return (bool) $args[$key];
    }
}
