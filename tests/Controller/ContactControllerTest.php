<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Application\Middleware\RateLimitMiddleware;
use App\Service\DomainStartPageService;
use App\Service\MailService;
use App\Service\TurnstileConfig;
use App\Service\TurnstileVerificationService;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    /**
     * @dataProvider contactRoutesProvider
     */
    public function testContactFormSendsMail(string $route): void {
        RateLimitMiddleware::resetPersistentStorage();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contacttest');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();
        putenv('TURNSTILE_SITE_KEY');
        putenv('TURNSTILE_SECRET_KEY');
        unset($_ENV['TURNSTILE_SITE_KEY'], $_ENV['TURNSTILE_SECRET_KEY']);

        $oldMainDomain = getenv('MAIN_DOMAIN');
        $oldEnvMainDomain = $_ENV['MAIN_DOMAIN'] ?? null;
        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';

        try {
            $mailer = new class () extends MailService {
                public array $args = [];
                public function __construct() {
                }
                public function sendContact(
                    string $to,
                    string $name,
                    string $replyTo,
                    string $message,
                    ?array $templateData = null,
                    ?string $fromEmail = null,
                    ?array $smtpOverride = null,
                    ?string $company = null
                ): void {
                    $this->args = [$to, $name, $replyTo, $message, $templateData, $fromEmail, $smtpOverride, $company];
                }
            };

            $pdo = $this->getDatabase();
            $stmt = $pdo->prepare(
                'INSERT INTO domain_start_pages(domain, start_page, email) VALUES(?, ?, ?)' .
                ' ON CONFLICT(domain) DO UPDATE SET start_page = excluded.start_page, email = excluded.email'
            );
            $stmt->execute(['main.test', 'landing', 'contact@main.test']);
            $pdo->prepare(
                'INSERT INTO domain_contact_templates(' .
                'domain, sender_name, recipient_html, recipient_text, sender_html, sender_text)' .
                ' VALUES(?, ?, ?, ?, ?, ?)' .
                ' ON CONFLICT(domain) DO UPDATE SET' .
                '     sender_name = excluded.sender_name,' .
                '     recipient_html = excluded.recipient_html,' .
                '     recipient_text = excluded.recipient_text,' .
                '     sender_html = excluded.sender_html,' .
                '     sender_text = excluded.sender_text'
            )->execute([
                'main.test',
                'Main Contact',
                '<p>{{ name }}</p>',
                'Name: {{ name }}',
                '<div>{{ message_html }}</div>',
                'Copy: {{ message_plain }}',
            ]);

            $body = json_encode([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'message' => 'Hello',
                'company' => '',
                'company_name' => 'Doe Labs',
            ], JSON_THROW_ON_ERROR);

            $request = $this->createRequest(
                'POST',
                $route,
                [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => 'token',
                ],
                [session_name() => session_id()]
            );
            $request->getBody()->write($body);
            $request->getBody()->rewind();
            $request = $request
                ->withUri($request->getUri()->withHost('main.test'))
                ->withAttribute('mailService', $mailer);

            $app = $this->getAppInstance();
            $response = $app->handle($request);

            $this->assertEquals(204, $response->getStatusCode());
            $this->assertSame([
                'contact@main.test',
                'John Doe',
                'john@example.com',
                'Hello',
                [
                    'domain' => 'main.test',
                    'sender_name' => 'Main Contact',
                    'recipient_html' => '<p>{{ name }}</p>',
                    'recipient_text' => 'Name: {{ name }}',
                    'sender_html' => '<div>{{ message_html }}</div>',
                    'sender_text' => 'Copy: {{ message_plain }}',
                ],
                'contact@main.test',
                null,
                'Doe Labs',
            ], $mailer->args);
        } finally {
            if ($oldMainDomain === false) {
                putenv('MAIN_DOMAIN');
            } else {
                putenv('MAIN_DOMAIN=' . $oldMainDomain);
            }
            if ($oldEnvMainDomain === null) {
                unset($_ENV['MAIN_DOMAIN']);
            } else {
                $_ENV['MAIN_DOMAIN'] = $oldEnvMainDomain;
            }
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function contactRoutesProvider(): array {
        return [
            ['/landing/contact'],
            ['/calserver/contact'],
        ];
    }

    public function testContactFormRejectsWhenMessageTooLong(): void {
        RateLimitMiddleware::resetPersistentStorage();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contacttoolong');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();

        putenv('TURNSTILE_SITE_KEY');
        putenv('TURNSTILE_SECRET_KEY');
        unset($_ENV['TURNSTILE_SITE_KEY'], $_ENV['TURNSTILE_SECRET_KEY']);

        $message = str_repeat('A', 161);
        $body = json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => $message,
            'company' => '',
            'company_name' => 'Org Inc.',
        ], JSON_THROW_ON_ERROR);

        $request = $this->createRequest(
            'POST',
            '/landing/contact',
            [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => 'token',
            ],
            [session_name() => session_id()]
        );
        $request->getBody()->write($body);
        $request->getBody()->rewind();

        $app = $this->getAppInstance();
        $response = $app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testContactFormRequiresCaptchaWhenConfigured(): void {
        RateLimitMiddleware::resetPersistentStorage();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contactcaptcha');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();

        putenv('TURNSTILE_SITE_KEY=site-key');
        putenv('TURNSTILE_SECRET_KEY=secret-key');
        $_ENV['TURNSTILE_SITE_KEY'] = 'site-key';
        $_ENV['TURNSTILE_SECRET_KEY'] = 'secret-key';

        $body = json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Hello',
            'company' => '',
            'company_name' => '',
        ], JSON_THROW_ON_ERROR);

        $request = $this->createRequest(
            'POST',
            '/landing/contact',
            [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => 'token',
            ],
            [session_name() => session_id()]
        );
        $request->getBody()->write($body);
        $request->getBody()->rewind();

        $app = $this->getAppInstance();
        $response = $app->handle($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testContactFormRejectsWhenCaptchaFails(): void {
        RateLimitMiddleware::resetPersistentStorage();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contactcaptcha2');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();

        $config = new TurnstileConfig('site', 'secret');
        $verifier = new class ($config) extends TurnstileVerificationService {
            public function __construct(TurnstileConfig $config) {
                parent::__construct($config);
            }

            public function verify(?string $token, ?string $ip = null): bool {
                return false;
            }
        };

        $body = json_encode([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Test',
            'company' => '',
            'company_name' => '',
            'cf-turnstile-response' => 'token-value',
        ], JSON_THROW_ON_ERROR);

        $request = $this->createRequest(
            'POST',
            '/landing/contact',
            [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => 'token',
            ],
            [session_name() => session_id()]
        );
        $request->getBody()->write($body);
        $request->getBody()->rewind();
        $request = $request
            ->withAttribute('turnstileConfig', $config)
            ->withAttribute('turnstileVerifier', $verifier);

        $app = $this->getAppInstance();
        $response = $app->handle($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testContactFormAcceptsWithValidCaptcha(): void {
        RateLimitMiddleware::resetPersistentStorage();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contactcaptcha3');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();

        $config = new TurnstileConfig('site', 'secret');
        $verifier = new class ($config) extends TurnstileVerificationService {
            public array $calls = [];

            public function __construct(TurnstileConfig $config) {
                parent::__construct($config);
            }

            public function verify(?string $token, ?string $ip = null): bool {
                $this->calls[] = [$token, $ip];

                return true;
            }
        };

        $mailer = new class () extends MailService {
            public array $sent = [];
            public function __construct() {
            }
            public function sendContact(
                string $to,
                string $name,
                string $replyTo,
                string $message,
                ?array $templateData = null,
                ?string $fromEmail = null,
                ?array $smtpOverride = null,
                ?string $company = null
            ): void {
                $this->sent[] = [$to, $name, $replyTo, $message, $company];
            }
        };

        $body = json_encode([
            'name' => 'Jane Roe',
            'email' => 'jane@example.com',
            'message' => 'Valid',
            'company' => '',
            'company_name' => '',
            'cf-turnstile-response' => 'token-value',
        ], JSON_THROW_ON_ERROR);

        $request = $this->createRequest(
            'POST',
            '/landing/contact',
            [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => 'token',
            ],
            [session_name() => session_id()]
        );
        $request->getBody()->write($body);
        $request->getBody()->rewind();
        $request = $request
            ->withAttribute('turnstileConfig', $config)
            ->withAttribute('turnstileVerifier', $verifier)
            ->withAttribute('mailService', $mailer);

        $app = $this->getAppInstance();
        $response = $app->handle($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(1, $verifier->calls);
        $this->assertSame('token-value', $verifier->calls[0][0]);
        $this->assertNotEmpty($mailer->sent);
    }

    public function testContactFormUsesDomainSpecificEmail(): void {
        RateLimitMiddleware::resetPersistentStorage();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contactdomain');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();
        putenv('TURNSTILE_SITE_KEY');
        putenv('TURNSTILE_SECRET_KEY');
        unset($_ENV['TURNSTILE_SITE_KEY'], $_ENV['TURNSTILE_SECRET_KEY']);

        $oldMainDomain = getenv('MAIN_DOMAIN');
        $oldEnvMainDomain = $_ENV['MAIN_DOMAIN'] ?? null;
        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';

        try {
            $pdo = $this->getDatabase();
            $domainService = new DomainStartPageService($pdo);
            $domainService->saveDomainConfig('main.test', 'landing', 'contact@domain.test');

            $mailer = new class () extends MailService {
                public array $args = [];
                public function __construct() {
                }
                public function sendContact(
                    string $to,
                    string $name,
                    string $replyTo,
                    string $message,
                    ?array $templateData = null,
                    ?string $fromEmail = null,
                    ?array $smtpOverride = null,
                    ?string $company = null
                ): void {
                    $this->args = [$to, $name, $replyTo, $message, $templateData, $fromEmail, $smtpOverride, $company];
                }
            };

            $body = json_encode([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'message' => 'Hi there',
                'company' => '',
                'company_name' => '',
            ], JSON_THROW_ON_ERROR);

            $request = $this->createRequest(
                'POST',
                '/landing/contact',
                [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => 'token',
                ],
                [session_name() => session_id()]
            );
            $request->getBody()->write($body);
            $request->getBody()->rewind();
            $request = $request
                ->withUri($request->getUri()->withHost('main.test'))
                ->withAttribute('mailService', $mailer);

            $app = $this->getAppInstance();
            $response = $app->handle($request);

            $this->assertEquals(204, $response->getStatusCode());
            $this->assertSame([
                'contact@domain.test',
                'Jane Doe',
                'jane@example.com',
                'Hi there',
                null,
                'contact@domain.test',
                null,
                null,
            ], $mailer->args);
        } finally {
            if ($oldMainDomain === false) {
                putenv('MAIN_DOMAIN');
            } else {
                putenv('MAIN_DOMAIN=' . $oldMainDomain);
            }
            if ($oldEnvMainDomain === null) {
                unset($_ENV['MAIN_DOMAIN']);
            } else {
                $_ENV['MAIN_DOMAIN'] = $oldEnvMainDomain;
            }
        }
    }

    public function testContactFormUsesDomainSpecificSmtpOverride(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contactsmtp');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();

        $oldMainDomain = getenv('MAIN_DOMAIN');
        $oldEnvMainDomain = $_ENV['MAIN_DOMAIN'] ?? null;
        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';

        try {
            $pdo = $this->getDatabase();
            $domainService = new DomainStartPageService($pdo);
            $domainService->saveDomainConfig('main.test', 'landing', 'contact@domain.test', [
                'smtp_host' => 'smtp.domain.test',
                'smtp_user' => 'mailer@domain.test',
                'smtp_pass' => 'supersecret',
                'smtp_port' => 2525,
                'smtp_encryption' => 'ssl',
            ]);

            $mailer = new class () extends MailService {
                public array $args = [];
                public function __construct() {
                }
                public function sendContact(
                    string $to,
                    string $name,
                    string $replyTo,
                    string $message,
                    ?array $templateData = null,
                    ?string $fromEmail = null,
                    ?array $smtpOverride = null,
                    ?string $company = null
                ): void {
                    $this->args = [$to, $name, $replyTo, $message, $templateData, $fromEmail, $smtpOverride, $company];
                }
            };

            $body = json_encode([
                'name' => 'Sam Sender',
                'email' => 'sam@example.com',
                'message' => 'Hi override',
                'company' => '',
                'company_name' => '',
            ], JSON_THROW_ON_ERROR);

            $request = $this->createRequest(
                'POST',
                '/landing/contact',
                [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => 'token',
                ],
                [session_name() => session_id()]
            );
            $request->getBody()->write($body);
            $request->getBody()->rewind();
            $request = $request
                ->withUri($request->getUri()->withHost('main.test'))
                ->withAttribute('mailService', $mailer);

            $app = $this->getAppInstance();
            $response = $app->handle($request);

            $this->assertEquals(204, $response->getStatusCode());
            $this->assertSame('contact@domain.test', $mailer->args[0]);
            $this->assertSame('contact@domain.test', $mailer->args[5]);
            $this->assertSame([
                'smtp_host' => 'smtp.domain.test',
                'smtp_user' => 'mailer@domain.test',
                'smtp_pass' => 'supersecret',
                'smtp_port' => 2525,
                'smtp_encryption' => 'ssl',
                'smtp_dsn' => null,
            ], $mailer->args[6]);
        } finally {
            if ($oldMainDomain === false) {
                putenv('MAIN_DOMAIN');
            } else {
                putenv('MAIN_DOMAIN=' . $oldMainDomain);
            }
            if ($oldEnvMainDomain === null) {
                unset($_ENV['MAIN_DOMAIN']);
            } else {
                $_ENV['MAIN_DOMAIN'] = $oldEnvMainDomain;
            }
        }
    }

    public function testContactFormHoneypotBlocksMail(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contacthoneypot');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();

        $oldMainDomain = getenv('MAIN_DOMAIN');
        $oldEnvMainDomain = $_ENV['MAIN_DOMAIN'] ?? null;
        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';

        try {
            $mailer = new class () extends MailService {
                public bool $called = false;
                public function __construct() {
                }
                public function sendContact(
                    string $to,
                    string $name,
                    string $replyTo,
                    string $message,
                    ?array $templateData = null,
                    ?string $fromEmail = null,
                    ?array $smtpOverride = null,
                    ?string $company = null
                ): void {
                    $this->called = true;
                }
            };

            $body = json_encode([
                'name' => 'Bot User',
                'email' => 'bot@example.com',
                'message' => 'Spam',
                'company' => 'Malicious Inc.',
                'company_name' => '',
            ], JSON_THROW_ON_ERROR);

            $request = $this->createRequest(
                'POST',
                '/landing/contact',
                [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => 'token',
                ],
                [session_name() => session_id()]
            );
            $request->getBody()->write($body);
            $request->getBody()->rewind();
            $request = $request
                ->withUri($request->getUri()->withHost('main.test'))
                ->withAttribute('mailService', $mailer);

            $app = $this->getAppInstance();
            $response = $app->handle($request);

            $this->assertEquals(204, $response->getStatusCode());
            $this->assertFalse($mailer->called, 'Honeypot submissions must not trigger mail delivery.');
        } finally {
            if ($oldMainDomain === false) {
                putenv('MAIN_DOMAIN');
            } else {
                putenv('MAIN_DOMAIN=' . $oldMainDomain);
            }
            if ($oldEnvMainDomain === null) {
                unset($_ENV['MAIN_DOMAIN']);
            } else {
                $_ENV['MAIN_DOMAIN'] = $oldEnvMainDomain;
            }
        }
    }

    public function testContactFormIgnoresInvalidDomainEmail(): void {
        RateLimitMiddleware::resetPersistentStorage();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contactinvalid');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();
        putenv('TURNSTILE_SITE_KEY');
        putenv('TURNSTILE_SECRET_KEY');
        unset($_ENV['TURNSTILE_SITE_KEY'], $_ENV['TURNSTILE_SECRET_KEY']);

        $oldMainDomain = getenv('MAIN_DOMAIN');
        $oldEnvMainDomain = $_ENV['MAIN_DOMAIN'] ?? null;
        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';

        try {
            $pdo = $this->getDatabase();
            $domainService = new DomainStartPageService($pdo);
            $domainService->saveDomainConfig('main.test', 'landing', 'not-an-email');

            $mailer = new class () extends MailService {
                public array $args = [];
                public function __construct() {
                }
                public function sendContact(
                    string $to,
                    string $name,
                    string $replyTo,
                    string $message,
                    ?array $templateData = null,
                    ?string $fromEmail = null,
                    ?array $smtpOverride = null,
                    ?string $company = null
                ): void {
                    $this->args = [$to, $name, $replyTo, $message, $templateData, $fromEmail, $smtpOverride, $company];
                }
            };

            $body = json_encode([
                'name' => 'Invalid Email',
                'email' => 'valid@example.com',
                'message' => 'Please ignore',
                'company' => '',
                'company_name' => '',
            ], JSON_THROW_ON_ERROR);

            $request = $this->createRequest(
                'POST',
                '/landing/contact',
                [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => 'token',
                ],
                [session_name() => session_id()]
            );
            $request->getBody()->write($body);
            $request->getBody()->rewind();
            $request = $request
                ->withUri($request->getUri()->withHost('main.test'))
                ->withAttribute('mailService', $mailer);

            $app = $this->getAppInstance();
            $response = $app->handle($request);

            $this->assertEquals(204, $response->getStatusCode());
            $this->assertSame([
                'not-an-email',
                'Invalid Email',
                'valid@example.com',
                'Please ignore',
                null,
                'not-an-email',
                null,
                null,
            ], $mailer->args);
        } finally {
            if ($oldMainDomain === false) {
                putenv('MAIN_DOMAIN');
            } else {
                putenv('MAIN_DOMAIN=' . $oldMainDomain);
            }
            if ($oldEnvMainDomain === null) {
                unset($_ENV['MAIN_DOMAIN']);
            } else {
                $_ENV['MAIN_DOMAIN'] = $oldEnvMainDomain;
            }
        }
    }
}
