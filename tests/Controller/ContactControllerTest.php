<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\MailService;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    public function testContactFormSendsMail(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('contacttest');
        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_COOKIE[session_name()] = session_id();

        $oldMainDomain = getenv('MAIN_DOMAIN');
        $oldEnvMainDomain = $_ENV['MAIN_DOMAIN'] ?? null;
        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';

        try {
            $mailer = new class extends MailService {
                public array $args = [];
                public function __construct()
                {
                }
                public function sendContact(
                    string $to,
                    string $name,
                    string $replyTo,
                    string $message,
                    ?array $templateData = null,
                    ?string $fromEmail = null
                ): void {
                    $this->args = [$to, $name, $replyTo, $message, $templateData, $fromEmail];
                }
            };

            $pdo = $this->getDatabase();
            $stmt = $pdo->prepare(
                'INSERT INTO domain_start_pages(domain, start_page, email) VALUES(?, ?, ?)
                 ON CONFLICT(domain) DO UPDATE SET start_page = excluded.start_page, email = excluded.email'
            );
            $stmt->execute(['main.test', 'landing', 'contact@main.test']);
            $pdo->prepare(
                'INSERT INTO domain_contact_templates(domain, sender_name, recipient_html, recipient_text, sender_html, sender_text)
                 VALUES(?, ?, ?, ?, ?, ?)
                 ON CONFLICT(domain) DO UPDATE SET
                    sender_name = excluded.sender_name,
                    recipient_html = excluded.recipient_html,
                    recipient_text = excluded.recipient_text,
                    sender_html = excluded.sender_html,
                    sender_text = excluded.sender_text'
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
            $pdo = new \PDO((string) getenv('POSTGRES_DSN'));
            $email = $pdo->query("SELECT imprint_email FROM tenants WHERE subdomain = 'main'")?->fetchColumn();
            $this->assertSame([
                (string) $email,
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

    public function testContactFormHoneypotBlocksMail(): void
    {
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
            $mailer = new class extends MailService {
                public bool $called = false;
                public function __construct()
                {
                }
                public function sendContact(
                    string $to,
                    string $name,
                    string $replyTo,
                    string $message,
                    ?array $templateData = null,
                    ?string $fromEmail = null
                ): void {
                    $this->called = true;
                }
            };

            $body = json_encode([
                'name' => 'Bot User',
                'email' => 'bot@example.com',
                'message' => 'Spam',
                'company' => 'Malicious Inc.',
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
}
