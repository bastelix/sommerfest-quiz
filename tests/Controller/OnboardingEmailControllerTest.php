<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Infrastructure\Database;
use App\Service\MailService;
use Tests\TestCase;

class OnboardingEmailControllerTest extends TestCase
{
    public function testPostRequiresCsrfToken(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';

        $request = $this->createRequest('POST', '/onboarding/email', ['Content-Type' => 'application/json']);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['email' => 'alice@example.com']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute(
            'mailService',
            new class extends MailService {
                public function __construct()
                {
                }

                public function sendDoubleOptIn(string $to, string $link): void
                {
                }
            }
        );

        $response = $app->handle($request);
        $this->assertSame(403, $response->getStatusCode());
        session_destroy();
    }

    public function testPostRespectsRateLimitAndCsrf(): void
    {
        $app = $this->getAppInstance();
        $pdo = Database::connectFromEnv();
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS email_confirmations (
                email TEXT NOT NULL,
                token TEXT NOT NULL,
                confirmed INTEGER NOT NULL DEFAULT 0,
                expires_at TEXT NOT NULL
            );
            SQL
        );
        $pdo->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_email_confirmations_token ON email_confirmations(token)'
        );
        $pdo->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_email_confirmations_email ON email_confirmations(email)'
        );
        session_start();
        $_SESSION['csrf_token'] = 'tok';

        $mailer = new class extends MailService {
            public array $sent = [];
            public function __construct() {}
            public function sendDoubleOptIn(string $to, string $link): void
            {
                $this->sent[] = [$to, $link];
            }
        };

        for ($i = 0; $i < 4; $i++) {
            $request = $this->createRequest('POST', '/onboarding/email', [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => 'tok',
            ]);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, json_encode(['email' => 'user@example.com']));
            rewind($stream);
            $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
            $request = $request->withAttribute('mailService', $mailer);
            $response = $app->handle($request);
            if ($i < 3) {
                $this->assertSame(204, $response->getStatusCode());
            } else {
                $this->assertSame(429, $response->getStatusCode());
            }
        }

        $this->assertCount(3, $mailer->sent);
        session_destroy();
    }
}
