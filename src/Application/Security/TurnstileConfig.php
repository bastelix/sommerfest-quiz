<?php

declare(strict_types=1);

namespace App\Application\Security;

final class TurnstileConfig
{
    public static function getSiteKey(): ?string
    {
        $key = getenv('TURNSTILE_SITE_KEY');
        if ($key === false) {
            $key = $_ENV['TURNSTILE_SITE_KEY'] ?? null;
        }

        if (!is_string($key)) {
            return null;
        }

        $key = trim($key);

        return $key === '' ? null : $key;
    }

    public static function getSecretKey(): ?string
    {
        $key = getenv('TURNSTILE_SECRET_KEY');
        if ($key === false) {
            $key = $_ENV['TURNSTILE_SECRET_KEY'] ?? null;
        }

        if (!is_string($key)) {
            return null;
        }

        $key = trim($key);

        return $key === '' ? null : $key;
    }

    public static function isEnabled(): bool
    {
        return self::getSiteKey() !== null && self::getSecretKey() !== null;
    }
}
