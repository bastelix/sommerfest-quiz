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

        $mailer = new class extends MailService {
            public array $args = [];
            public function __construct()
            {
            }
            public function sendContact(string $to, string $name, string $replyTo, string $message): void
            {
                $this->args = [$to, $name, $replyTo, $message];
            }
        };

        $body = json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Hello',
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
        $request = $request->withAttribute('mailService', $mailer);

        $app = $this->getAppInstance();
        $response = $app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
        $profileFile = dirname(__DIR__, 2) . '/data/profile.json';
        $profile = json_decode((string) file_get_contents($profileFile), true);
        $this->assertSame([
            (string) $profile['imprint_email'],
            'John Doe',
            'john@example.com',
            'Hello',
        ], $mailer->args);
    }
}
