<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\MailService;
use RuntimeException;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    public function testContactFailsWithoutMailConfiguration(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $request = $this->createRequest('POST', '/landing/contact', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => 'tok',
        ]);
        $request->getBody()->write(json_encode([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'message' => 'Hi',
        ]));
        $request->getBody()->rewind();

        $mailer = new class extends MailService {
            public function __construct()
            {
            }

            public function sendContact(string $to, string $name, string $email, string $message): void
            {
                throw new RuntimeException('no smtp');
            }
        };
        $request = $request->withAttribute('mailService', $mailer);

        $response = $app->handle($request);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame('Mailversand fehlgeschlagen', (string) $response->getBody());
        session_destroy();
    }
}
