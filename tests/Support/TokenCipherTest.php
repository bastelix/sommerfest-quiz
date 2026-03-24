<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\TokenCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TokenCipherTest extends TestCase
{
    public function testCanBeConstructedWithoutSecret(): void
    {
        $cipher = new TokenCipher('');
        $this->assertInstanceOf(TokenCipher::class, $cipher);
    }

    public function testEncryptThrowsWithoutSecret(): void
    {
        $cipher = new TokenCipher('');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dashboard token secret is not configured.');
        $cipher->encrypt('test');
    }

    public function testDecryptThrowsWithoutSecret(): void
    {
        $cipher = new TokenCipher('');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dashboard token secret is not configured.');
        $cipher->decrypt('dGVzdA==');
    }

    public function testEncryptReturnsNullForEmptyInput(): void
    {
        $cipher = new TokenCipher('');
        $this->assertNull($cipher->encrypt(null));
        $this->assertNull($cipher->encrypt(''));
    }

    public function testDecryptReturnsNullForEmptyInput(): void
    {
        $cipher = new TokenCipher('');
        $this->assertNull($cipher->decrypt(null));
        $this->assertNull($cipher->decrypt(''));
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $cipher = new TokenCipher('test-secret');
        $plaintext = 'hello world';

        $encrypted = $cipher->encrypt($plaintext);
        $this->assertNotNull($encrypted);
        $this->assertNotSame($plaintext, $encrypted);

        $decrypted = $cipher->decrypt($encrypted);
        $this->assertSame($plaintext, $decrypted);
    }

    public function testDecryptReturnsNullForInvalidPayload(): void
    {
        $cipher = new TokenCipher('test-secret');
        $this->assertNull($cipher->decrypt('not-valid-base64!!!'));
        $this->assertNull($cipher->decrypt(base64_encode('tooshort')));
    }
}
