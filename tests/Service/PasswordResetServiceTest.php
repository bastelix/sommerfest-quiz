<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\PasswordResetService;
use App\Service\UserService;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    public function testCreateAndConsumeToken(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec('ALTER TABLE users ADD COLUMN email TEXT');
        $pdo->exec('ALTER TABLE users ADD COLUMN active INTEGER DEFAULT 1');
        $pdo->exec('CREATE TABLE password_resets(user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)');
        $users = new UserService($pdo);
        $users->create('alice', 'secret', 'alice@example.com');

        $logger = new ArrayLogger();
        $svc = new PasswordResetService($pdo, 3600, 'secret', $logger);
        $token = $svc->createToken(1);
        $this->assertNotEmpty($token);
        $hash = $pdo->query('SELECT token_hash FROM password_resets')->fetchColumn();
        $this->assertSame(hash_hmac('sha256', $token, 'secret'), $hash);
        $this->assertTrue($logger->has('info', 'Password reset token created'));

        $userId = $svc->consumeToken($token);
        $this->assertSame(1, $userId);
        $this->assertTrue($logger->has('info', 'Password reset token consumed'));

        $this->assertNull($svc->consumeToken($token));
        $this->assertTrue($logger->has('warning', 'Password reset token not found'));
    }

    public function testTokenExpires(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec('ALTER TABLE users ADD COLUMN email TEXT');
        $pdo->exec('ALTER TABLE users ADD COLUMN active INTEGER DEFAULT 1');
        $pdo->exec('CREATE TABLE password_resets(user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)');
        $users = new UserService($pdo);
        $users->create('bob', 'secret', 'bob@example.com');

        $logger = new ArrayLogger();
        $svc = new PasswordResetService($pdo, 3600, 'secret', $logger);
        $token = $svc->createToken(1);
        $pdo->exec("UPDATE password_resets SET expires_at = '2000-01-01 00:00:00'");

        $this->assertNull($svc->consumeToken($token));
        $this->assertTrue(
            $logger->has('warning', 'expired') ||
            $logger->has('warning', 'token not found')
        );
    }

    public function testOldTokenInvalidated(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec('ALTER TABLE users ADD COLUMN email TEXT');
        $pdo->exec('ALTER TABLE users ADD COLUMN active INTEGER DEFAULT 1');
        $pdo->exec('CREATE TABLE password_resets(user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)');
        $users = new UserService($pdo);
        $users->create('carol', 'secret', 'carol@example.com');

        $svc = new PasswordResetService($pdo, 3600, 'secret');
        $first = $svc->createToken(1);
        $second = $svc->createToken(1);

        $this->assertNull($svc->consumeToken($first));
        $this->assertSame(1, $svc->consumeToken($second));
    }
}
