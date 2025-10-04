<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lightweight .env reader that tolerates characters parse_ini_file() rejects.
 */
final class EnvLoader
{
    /**
     * Load variables from an .env-style file without mutating the environment.
     *
     * @return array<string, string>
     */
    public static function load(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $variables = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $rawValue] = explode('=', $line, 2);
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $variables[$name] = self::normaliseValue($rawValue);
        }

        return $variables;
    }

    /**
     * Load variables and populate $_ENV/putenv without overriding existing values.
     *
     * @return array<string, string>
     */
    public static function loadAndSet(string $path): array
    {
        $variables = self::load($path);

        foreach ($variables as $name => $value) {
            if (getenv($name) !== false) {
                continue;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }

        return $variables;
    }

    private static function normaliseValue(string $value): string
    {
        $value = ltrim($value);

        if ($value === '') {
            return '';
        }

        $quoteChar = $value[0];
        if ($quoteChar === '\'' || $quoteChar === '"') {
            $value = substr($value, 1);
            $endPos = strrpos($value, $quoteChar);
            if ($endPos !== false) {
                $value = substr($value, 0, $endPos);
            }

            return str_replace('\\' . $quoteChar, $quoteChar, $value);
        }

        $buffer = '';
        $length = strlen($value);
        $escape = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '#' || $char === ';') {
                break;
            }

            $buffer .= $char;
        }

        if ($escape) {
            $buffer .= '\\';
        }

        return rtrim($buffer);
    }
}
