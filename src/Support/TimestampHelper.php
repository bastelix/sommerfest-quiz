<?php

declare(strict_types=1);

namespace App\Support;

final class TimestampHelper
{
    private function __construct()
    {
    }

    /**
     * Normalize a timestamp-like value to a positive integer.
     *
     * @param mixed $value
     *
     * @return int|null
     */
    public static function normalize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        } elseif (is_int($value)) {
            return $value > 0 ? $value : null;
        } elseif (is_float($value)) {
            return self::normalizeNumeric($value);
        } elseif (is_string($value)) {
            return self::normalizeString($value);
        } elseif ($value instanceof \Stringable) {
            return self::normalizeString((string) $value);
        }

        return null;
    }

    private static function normalizeString(string $value): ?int
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '0') {
            return null;
        }

        if (!is_numeric($trimmed)) {
            return null;
        }

        return self::normalizeNumeric((float) $trimmed);
    }

    private static function normalizeNumeric(float $value): ?int
    {
        if (!is_finite($value) || $value <= 0.0) {
            return null;
        }

        $timestamp = (int) round($value);

        return $timestamp > 0 ? $timestamp : null;
    }
}
