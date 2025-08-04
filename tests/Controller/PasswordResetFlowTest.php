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
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        Migrator::migrate($pdo, __DIR__ . '/../../migrations');
        $pdo->exec('ALTER TABLE users ADD COLUMN email TEXT');
        $pdo->exec('ALTER TABLE users ADD COLUMN active INTEGER DEFAULT 1');
        $pdo->exec('CREATE TABLE password_resets(user_id INTEGER NOT NULL, token TEXT NOT NULL, expires_at TEXT NOT NULL)');
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
        $request = $this->createRequest('POST', '/password/reset/request', [
            'X-CSRF-Token' => 'tok',
        ])
            ->withAttribute('mailService', $mailer)
            ->withParsedBody(['username' => 'alice']);
        $response = $app->handle($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);

        $link = $mailer->sent[0]['link'];
        $token = $pdo->query('SELECT token FROM password_resets')->fetchColumn();
        $this->assertIsString($token);
        $this->assertStringContainsString($token, $link);

        $confirm = $this->createRequest('POST', '/password/reset/confirm', [
            'X-CSRF-Token' => 'tok',
        ])
            ->withParsedBody(['token' => $token, 'password' => 'newpass']);
        $resp2 = $app->handle($confirm);
        $this->assertSame(204, $resp2->getStatusCode());

        $updated = $userService->getByUsername('alice');
        $this->assertIsArray($updated);
        $this->assertTrue(password_verify('newpass', (string)$updated['password']));
    }
}
