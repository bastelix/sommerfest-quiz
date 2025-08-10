<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use App\Service\UserService;
use App\Service\MailService;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    public function testFullResetFlow(): void
    {
        putenv('PASSWORD_RESET_SECRET=secret');
        $_ENV['PASSWORD_RESET_SECRET'] = 'secret';
        putenv('POSTGRES_DSN=');
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        unset($_ENV['POSTGRES_DSN'], $_ENV['POSTGRES_USER'], $_ENV['POSTGRES_PASSWORD']);
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN email TEXT');
        } catch (\PDOException $e) {
        }
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN active INTEGER DEFAULT 1');
        } catch (\PDOException $e) {
        }
        try {
            $pdo->exec('CREATE TABLE password_resets(user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)');
        } catch (\PDOException $e) {
        }
        $userService = new UserService($pdo);
        $userService->create('alice', 'oldpass', 'alice@example.com', Roles::ADMIN);

        $mailer = new class extends MailService {
            public array $sent = [];
            public function __construct() {}
            public function sendPasswordReset(string $to, string $link): void
            {
                $this->sent[] = ['to' => $to, 'link' => $link];
            }
        };

        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/password/reset/request')
            ->withAttribute('mailService', $mailer)
            ->withParsedBody(['username' => 'alice', 'csrf_token' => 'tok']);
        $response = $app->handle($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);

        $link = $mailer->sent[0]['link'];
        $pos = strrpos($link, 'token=');
        $token = $pos === false ? '' : substr($link, $pos + 6);
        $this->assertNotSame('', $token, 'Token not found in link: ' . $link);
        $hash = hash_hmac('sha256', $token, 'secret');
        $dbHash = $pdo->query('SELECT token_hash FROM password_resets')->fetchColumn();
        $this->assertSame($hash, $dbHash);

        $confirm = $this->createRequest('POST', '/password/reset/confirm')
            ->withParsedBody([
                'token' => $token,
                'password' => 'Str0ngPass1',
                'csrf_token' => 'tok',
            ]);
        $resp2 = $app->handle($confirm);
        $this->assertSame(204, $resp2->getStatusCode());

        $updated = $userService->getByUsername('alice');
        $this->assertIsArray($updated);
        $this->assertTrue(password_verify('Str0ngPass1', (string)$updated['password']));
    }

    public function testRejectWeakPassword(): void
    {
        putenv('PASSWORD_RESET_SECRET=secret');
        $_ENV['PASSWORD_RESET_SECRET'] = 'secret';
        putenv('POSTGRES_DSN=');
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        unset($_ENV['POSTGRES_DSN'], $_ENV['POSTGRES_USER'], $_ENV['POSTGRES_PASSWORD']);
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN email TEXT');
        } catch (\PDOException $e) {
        }
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN active INTEGER DEFAULT 1');
        } catch (\PDOException $e) {
        }
        try {
            $pdo->exec('CREATE TABLE password_resets(user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)');
        } catch (\PDOException $e) {
        }
        $userService = new UserService($pdo);
        $userService->create('alice', 'oldpass', 'alice@example.com', Roles::ADMIN);

        $mailer = new class extends MailService {
            public array $sent = [];
            public function __construct() {}
            public function sendPasswordReset(string $to, string $link): void
            {
                $this->sent[] = ['to' => $to, 'link' => $link];
            }
        };

        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/password/reset/request')
            ->withAttribute('mailService', $mailer)
            ->withParsedBody(['username' => 'alice', 'csrf_token' => 'tok']);
        $response = $app->handle($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);

        $link = $mailer->sent[0]['link'];
        $pos = strrpos($link, 'token=');
        $token = $pos === false ? '' : substr($link, $pos + 6);
        $this->assertNotSame('', $token, 'Token not found in link: ' . $link);

        $confirm = $this->createRequest('POST', '/password/reset/confirm')
            ->withParsedBody([
                'token' => $token,
                'password' => 'weak',
                'csrf_token' => 'tok',
            ]);
        $resp2 = $app->handle($confirm);
        $this->assertSame(400, $resp2->getStatusCode());

        $updated = $userService->getByUsername('alice');
        $this->assertIsArray($updated);
        $this->assertTrue(password_verify('oldpass', (string)$updated['password']));
    }
}
