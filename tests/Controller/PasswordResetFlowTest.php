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
    public function testFullResetFlow(): void {
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
            $pdo->exec(
                'CREATE TABLE password_resets(' .
                'user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)'
            );
        } catch (\PDOException $e) {
        }
        try {
            $pdo->exec(
                'CREATE TABLE user_sessions(' .
                'user_id INTEGER NOT NULL, session_id TEXT PRIMARY KEY, created_at TEXT)'
            );
        } catch (\PDOException $e) {
        }
        $userService = new UserService($pdo);
        $userService->create('alice', 'oldpass', 'alice@example.com', Roles::ADMIN);

        $mailer = new class extends MailService
        {
            public array $sent = [];

            public function __construct() {
            }

            public function sendPasswordReset(string $to, string $link): void {
                $this->sent[] = ['to' => $to, 'link' => $link];
            }
        };

        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/password/reset/request')
            ->withAttribute('mailService', $mailer)
            ->withParsedBody(['username' => 'alice', 'csrf_token' => 'tok']);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Link gesendet', $body);
        $this->assertStringContainsString('/login', $body);
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
                'password_repeat' => 'Str0ngPass1',
                'csrf_token' => 'tok',
            ]);
        $_POST['csrf_token'] = 'tok';
        $resp2 = $app->handle($confirm);
        $_POST = [];
        $this->assertSame(302, $resp2->getStatusCode());
        $this->assertSame('/login?reset=1', $resp2->getHeaderLine('Location'));

        $login = $app->handle($this->createRequest('GET', '/login?reset=1'));
        $this->assertSame(200, $login->getStatusCode());
        $body = (string) $login->getBody();
        $this->assertStringContainsString('Passwort erfolgreich', $body);

        $updated = $userService->getByUsername('alice');
        $this->assertIsArray($updated);
        $this->assertTrue(password_verify('Str0ngPass1', (string)$updated['password']));
    }

    public function testRejectWeakPassword(): void {
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
            $pdo->exec(
                'CREATE TABLE password_resets(' .
                'user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)'
            );
        } catch (\PDOException $e) {
        }
        $userService = new UserService($pdo);
        $userService->create('alice', 'oldpass', 'alice@example.com', Roles::ADMIN);

        $mailer = new class extends MailService
        {
            public array $sent = [];

            public function __construct() {
            }

            public function sendPasswordReset(string $to, string $link): void {
                $this->sent[] = ['to' => $to, 'link' => $link];
            }
        };

        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/password/reset/request')
            ->withAttribute('mailService', $mailer)
            ->withParsedBody(['username' => 'alice', 'csrf_token' => 'tok']);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);

        $link = $mailer->sent[0]['link'];
        $pos = strrpos($link, 'token=');
        $token = $pos === false ? '' : substr($link, $pos + 6);
        $this->assertNotSame('', $token, 'Token not found in link: ' . $link);

        $confirm = $this->createRequest('POST', '/password/reset/confirm')
            ->withParsedBody([
                'token' => $token,
                'password' => 'weak',
                'password_repeat' => 'weak',
                'csrf_token' => 'tok',
            ]);
        $_POST['csrf_token'] = 'tok';
        $resp2 = $app->handle($confirm);
        $_POST = [];
        $this->assertSame(400, $resp2->getStatusCode());
        $body = (string) $resp2->getBody();
        $this->assertStringContainsString('Passwort konnte nicht geändert werden.', $body);

        $updated = $userService->getByUsername('alice');
        $this->assertIsArray($updated);
        $this->assertTrue(password_verify('oldpass', (string)$updated['password']));
    }

    public function testRejectMismatchedPasswords(): void {
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
            $pdo->exec(
                'CREATE TABLE password_resets(' .
                'user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)'
            );
        } catch (\PDOException $e) {
        }
        $userService = new UserService($pdo);
        $userService->create('alice', 'oldpass', 'alice@example.com', Roles::ADMIN);

        $mailer = new class extends MailService
        {
            public array $sent = [];

            public function __construct() {
            }

            public function sendPasswordReset(string $to, string $link): void {
                $this->sent[] = ['to' => $to, 'link' => $link];
            }
        };

        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/password/reset/request')
            ->withAttribute('mailService', $mailer)
            ->withParsedBody(['username' => 'alice', 'csrf_token' => 'tok']);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);

        $link = $mailer->sent[0]['link'];
        $pos = strrpos($link, 'token=');
        $token = $pos === false ? '' : substr($link, $pos + 6);
        $this->assertNotSame('', $token, 'Token not found in link: ' . $link);

        $confirm = $this->createRequest('POST', '/password/reset/confirm')
            ->withParsedBody([
                'token' => $token,
                'password' => 'Str0ngPass1',
                'password_repeat' => 'different',
                'csrf_token' => 'tok',
            ]);
        $_POST['csrf_token'] = 'tok';
        $resp2 = $app->handle($confirm);
        $_POST = [];
        $this->assertSame(400, $resp2->getStatusCode());
        $body = (string) $resp2->getBody();
        $this->assertStringContainsString('Passwörter stimmen nicht überein.', $body);

        $updated = $userService->getByUsername('alice');
        $this->assertIsArray($updated);
        $this->assertTrue(password_verify('oldpass', (string)$updated['password']));
    }

    public function testRejectMissingPasswordRepeat(): void {
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
            $pdo->exec(
                'CREATE TABLE password_resets(' .
                'user_id INTEGER NOT NULL, token_hash TEXT NOT NULL, expires_at TEXT NOT NULL)'
            );
        } catch (\PDOException $e) {
        }
        $userService = new UserService($pdo);
        $userService->create('alice', 'oldpass', 'alice@example.com', Roles::ADMIN);

        $mailer = new class extends MailService
        {
            public array $sent = [];

            public function __construct() {
            }

            public function sendPasswordReset(string $to, string $link): void {
                $this->sent[] = ['to' => $to, 'link' => $link];
            }
        };

        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/password/reset/request')
            ->withAttribute('mailService', $mailer)
            ->withParsedBody(['username' => 'alice', 'csrf_token' => 'tok']);
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);

        $link = $mailer->sent[0]['link'];
        $pos = strrpos($link, 'token=');
        $token = $pos === false ? '' : substr($link, $pos + 6);
        $this->assertNotSame('', $token, 'Token not found in link: ' . $link);

        $confirm = $this->createRequest('POST', '/password/reset/confirm')
            ->withParsedBody([
                'token' => $token,
                'password' => 'Str0ngPass1',
                'csrf_token' => 'tok',
            ]);
        $_POST['csrf_token'] = 'tok';
        $resp2 = $app->handle($confirm);
        $_POST = [];
        $this->assertSame(400, $resp2->getStatusCode());
        $body = (string) $resp2->getBody();
        $this->assertStringContainsString('Passwörter stimmen nicht überein.', $body);

        $updated = $userService->getByUsername('alice');
        $this->assertIsArray($updated);
        $this->assertTrue(password_verify('oldpass', (string)$updated['password']));
    }
}
