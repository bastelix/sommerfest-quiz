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
     */
    public static function normalize($value): ?int
    {
        if ($value === null) {
            return null;
        } elseif (is_int($value)) {
            return $value > 0 ? $value : null;
        } elseif (is_float($value)) {
            if (!is_finite($value) || $value <= 0.0) {
                return null;
            }

            $timestamp = (int) round($value);

            return $timestamp > 0 ? $timestamp : null;
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === '0') {
                return null;
            }

            if (!is_numeric($trimmed)) {
                return null;
            }

            $numericValue = (float) $trimmed;
            if (!is_finite($numericValue) || $numericValue <= 0.0) {
                return null;
            }

            $timestamp = (int) round($numericValue);

            return $timestamp > 0 ? $timestamp : null;
        }

        return null;
    }
}
