<?php

declare(strict_types=1);

namespace App\Service;

class TurnstileConfig
{
    private ?string $siteKey;
    private ?string $secretKey;
    private bool $enabled;

    public function __construct(?string $siteKey, ?string $secretKey, bool $enabled = true)
    {
        $siteKey = $this->normalize($siteKey);
        $secretKey = $this->normalize($secretKey);
        $this->enabled = $enabled;
        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
    }

    public static function fromEnv(): self
    {
        $siteKey = getenv('TURNSTILE_SITE_KEY') ?: null;
        $secretKey = getenv('TURNSTILE_SECRET_KEY') ?: null;

        return new self($siteKey, $secretKey);
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->siteKey !== null && $this->secretKey !== null;
    }

    public function getSiteKey(): ?string
    {
        return $this->siteKey;
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return $value;
    }
}
