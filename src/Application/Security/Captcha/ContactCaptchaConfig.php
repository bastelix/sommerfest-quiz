<?php

declare(strict_types=1);

namespace App\Application\Security\Captcha;

final class ContactCaptchaConfig
{
    public const PROVIDER_TURNSTILE = 'turnstile';

    private bool $enabled;
    private string $provider;
    private ?string $siteKey;
    private ?string $secret;

    private function __construct(bool $enabled, string $provider, ?string $siteKey, ?string $secret)
    {
        $this->enabled = $enabled;
        $this->provider = $provider;
        $this->siteKey = $siteKey;
        $this->secret = $secret;
    }

    public static function fromEnv(): self
    {
        $provider = strtolower(trim((string) (getenv('CONTACT_CAPTCHA_PROVIDER') ?: self::PROVIDER_TURNSTILE)));
        if ($provider === '') {
            $provider = self::PROVIDER_TURNSTILE;
        }

        $siteKey = null;
        $secret = null;
        $enabled = false;

        if ($provider === self::PROVIDER_TURNSTILE) {
            $siteKey = trim((string) (getenv('TURNSTILE_SITE_KEY') ?: ''));
            $secret = trim((string) (getenv('TURNSTILE_SECRET_KEY') ?: ''));
            $enabled = $siteKey !== '' && $secret !== '';
        }

        return new self($enabled, $provider, $siteKey !== '' ? $siteKey : null, $secret !== '' ? $secret : null);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getSiteKey(): ?string
    {
        return $this->siteKey;
    }

    public function createVerifier(): ?CaptchaVerifierInterface
    {
        if (!$this->enabled) {
            return null;
        }

        if ($this->provider === self::PROVIDER_TURNSTILE && $this->secret !== null) {
            return new TurnstileCaptchaVerifier($this->secret);
        }

        return null;
    }
}
