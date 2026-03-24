<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Provides symmetric encryption for short-lived dashboard share tokens.
 */
class TokenCipher
{
    private const CIPHER = 'aes-256-gcm';

    private string $secret;
    private string $key;
    private int $ivLength;
    private bool $initialized = false;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? (getenv('DASHBOARD_TOKEN_SECRET') ?: getenv('PASSWORD_RESET_SECRET') ?: '');
    }

    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        if ($this->secret === '') {
            throw new RuntimeException('Dashboard token secret is not configured.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength <= 0) {
            throw new RuntimeException('Unable to determine IV length for dashboard token cipher.');
        }

        $this->ivLength = $ivLength;
        $this->key = hash('sha256', $this->secret, true);
        $this->initialized = true;
    }

    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $this->ensureInitialized();

        $iv = random_bytes($this->ivLength);
        $tag = '';
        $cipher = openssl_encrypt($value, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('Failed to encrypt dashboard token.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $this->ensureInitialized();

        $data = base64_decode($payload, true);
        if ($data === false) {
            return null;
        }

        $expectedLength = $this->ivLength + 16;
        if (strlen($data) <= $expectedLength) {
            return null;
        }

        $iv = substr($data, 0, $this->ivLength);
        $tag = substr($data, $this->ivLength, 16);
        $cipher = substr($data, $expectedLength);

        $plain = openssl_decrypt($cipher, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }
}
