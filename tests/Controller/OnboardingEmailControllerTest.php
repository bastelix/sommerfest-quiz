<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Infrastructure\Database;
use App\Service\MailService;
use Tests\TestCase;

class OnboardingEmailControllerTest extends TestCase
{
    public function testPostRequiresCsrfToken(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        unset($_SESSION['rate:/onboarding/email']);

        $request = $this->createRequest('POST', '/onboarding/email', ['Content-Type' => 'application/json']);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['email' => 'alice@example.com']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute(
            'mailService',
            new class extends MailService {
                public function __construct() {
                }

                public function sendDoubleOptIn(string $to, string $link): void {
                }
            }
        );

        $response = $app->handle($request);
        $this->assertSame(403, $response->getStatusCode());
        session_destroy();
    }

    public function testPostRespectsRateLimitAndCsrf(): void {
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
        unset($_SESSION['rate:/onboarding/email']);

        $mailer = new class extends MailService {
            public array $sent = [];
            public function __construct() {
            }
            public function sendDoubleOptIn(string $to, string $link): void {
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

    public function testPostRejectsInvalidEmailAddress(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        unset($_SESSION['rate:/onboarding/email']);

        $request = $this->createRequest('POST', '/onboarding/email', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['email' => 'invalid']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));

        $response = $app->handle($request);
        $this->assertSame(400, $response->getStatusCode());
        session_destroy();
    }

    public function testPostAcceptsTrimmedValidEmail(): void {
        $app = $this->getAppInstance();
        $this->setupEmailConfirmations();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        unset($_SESSION['rate:/onboarding/email']);

        $mailer = new class extends MailService {
            public array $sent = [];
            public function __construct() {
            }
            public function sendDoubleOptIn(string $to, string $link): void {
                $this->sent[] = [$to, $link];
            }
        };

        $request = $this->createRequest('POST', '/onboarding/email', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['email' => '  user@example.com  ']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute('mailService', $mailer);

        $response = $app->handle($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);
        $this->assertSame('user@example.com', $mailer->sent[0][0]);
        session_destroy();
    }

    public function testTokenCreationStoresTokenAndSendsMail(): void {
        $app = $this->getAppInstance();
        $pdo = $this->setupEmailConfirmations();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        unset($_SESSION['rate:/onboarding/email']);

        $mailer = new class extends MailService {
            public array $sent = [];
            public function __construct() {
            }
            public function sendDoubleOptIn(string $to, string $link): void {
                $this->sent[] = [$to, $link];
            }
        };

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
        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);

        $row = $pdo->query('SELECT email, token, confirmed FROM email_confirmations')->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('user@example.com', $row['email']);
        $this->assertSame('0', (string) $row['confirmed']);

        $link = $mailer->sent[0][1];
        $this->assertStringContainsString($row['token'], $link);
        session_destroy();
    }

    public function testLinkUsesForwardedHeaders(): void {
        $app = $this->getAppInstance();
        $pdo = $this->setupEmailConfirmations();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        unset($_SESSION['rate:/onboarding/email']);

        $mailer = new class extends MailService {
            public array $sent = [];
            public function __construct() {
            }
            public function sendDoubleOptIn(string $to, string $link): void {
                $this->sent[] = [$to, $link];
            }
        };

        $request = $this->createRequest('POST', '/onboarding/email', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'quizrace.app',
            'X-Forwarded-Port' => '443',
        ]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['email' => 'user@example.com']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute('mailService', $mailer);

        $response = $app->handle($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(1, $mailer->sent);

        $link = $mailer->sent[0][1];
        $this->assertStringStartsWith('https://quizrace.app/onboarding/email/confirm?token=', $link);
        session_destroy();
    }

    public function testLinkAndRedirectRespectBasePath(): void {
        $oldBase = getenv('BASE_PATH');
        putenv('BASE_PATH=/base');
        $_ENV['BASE_PATH'] = '/base';

        $app = $this->getAppInstance();
        $pdo = $this->setupEmailConfirmations();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        unset($_SESSION['rate:/onboarding/email']);

        $mailer = new class extends MailService {
            public array $sent = [];
            public function __construct() {
            }
            public function sendDoubleOptIn(string $to, string $link): void {
                $this->sent[] = [$to, $link];
            }
        };

        $request = $this->createRequest('POST', '/base/onboarding/email', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $request = $request->withUri($request->getUri()->withBasePath('/base'));
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['email' => 'user@example.com']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute('mailService', $mailer);

        $response = $app->handle($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertStringStartsWith('/base/onboarding/email/confirm?token=', $mailer->sent[0][1]);

        $token = (string) $pdo->query('SELECT token FROM email_confirmations')->fetchColumn();
        $confirm = $this->createRequest('GET', '/base/onboarding/email/confirm?token=' . $token);
        $confirm = $confirm->withUri($confirm->getUri()->withBasePath('/base'));
        $confirmResp = $app->handle($confirm);
        $this->assertSame(302, $confirmResp->getStatusCode());
        $this->assertStringStartsWith(
            '/base/onboarding?email=user%40example.com&verified=1',
            $confirmResp->getHeaderLine('Location')
        );

        session_destroy();

        if ($oldBase === false) {
            putenv('BASE_PATH');
            unset($_ENV['BASE_PATH']);
        } else {
            putenv('BASE_PATH=' . $oldBase);
            $_ENV['BASE_PATH'] = $oldBase;
        }
    }

    public function testConfirmValidAndInvalidTokens(): void {
        $app = $this->getAppInstance();
        $pdo = $this->setupEmailConfirmations();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        unset($_SESSION['rate:/onboarding/email']);

        $mailer = new class extends MailService {
            public function __construct() {
            }
            public function sendDoubleOptIn(string $to, string $link): void {
            }
        };
        $request = $this->createRequest('POST', '/onboarding/email', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['email' => 'user@example.com']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute('mailService', $mailer);
        $app->handle($request);

        $token = (string) $pdo->query('SELECT token FROM email_confirmations')->fetchColumn();
        $confirm = $this->createRequest('GET', '/onboarding/email/confirm?token=' . $token);
        $response = $app->handle($confirm);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString(
            '/onboarding?email=user%40example.com&verified=1',
            $response->getHeaderLine('Location')
        );
        $count = (int) $pdo
            ->query('SELECT COUNT(*) FROM email_confirmations WHERE token = ' . $pdo->quote($token))
            ->fetchColumn();
        $this->assertSame(0, $count);

        $bad = $this->createRequest('GET', '/onboarding/email/confirm?token=invalid');
        $badResp = $app->handle($bad);
        $this->assertSame(400, $badResp->getStatusCode());
        session_destroy();
    }

    public function testStatusEndpointReturns404AfterTokenRemoval(): void {
        $app = $this->getAppInstance();
        $pdo = $this->setupEmailConfirmations();
        session_start();
        $_SESSION['csrf_token'] = 'tok';

        $mailer = new class extends MailService {
            public function __construct() {
            }
            public function sendDoubleOptIn(string $to, string $link): void {
            }
        };
        $request = $this->createRequest('POST', '/onboarding/email', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, json_encode(['email' => 'user@example.com']));
        rewind($stream);
        $request = $request->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $request = $request->withAttribute('mailService', $mailer);
        $app->handle($request);

        $status1 = $app->handle($this->createRequest('GET', '/onboarding/email/status?email=user@example.com'));
        $this->assertSame(404, $status1->getStatusCode());

        $token = (string) $pdo->query('SELECT token FROM email_confirmations')->fetchColumn();
        $app->handle($this->createRequest('GET', '/onboarding/email/confirm?token=' . $token));

        $status2 = $app->handle($this->createRequest('GET', '/onboarding/email/status?email=user@example.com'));
        $this->assertSame(404, $status2->getStatusCode());

        $status3 = $app->handle($this->createRequest('GET', '/onboarding/email/status?email=other@example.com'));
        $this->assertSame(404, $status3->getStatusCode());
        session_destroy();
    }

    private function setupEmailConfirmations(): \PDO {
        $pdo = Database::connectFromEnv();
        $pdo->exec('DROP TABLE IF EXISTS email_confirmations');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE email_confirmations (
                email TEXT NOT NULL,
                token TEXT NOT NULL,
                confirmed INTEGER NOT NULL DEFAULT 0,
                expires_at TEXT NOT NULL
            );
            SQL
        );
        $pdo->exec('CREATE UNIQUE INDEX idx_email_confirmations_token ON email_confirmations(token)');
        $pdo->exec('CREATE UNIQUE INDEX idx_email_confirmations_email ON email_confirmations(email)');
        return $pdo;
    }
}
