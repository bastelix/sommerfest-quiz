<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use Slim\Psr7\Uri;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use App\Service\MailService;
use PDO;

class ProfileWelcomeControllerTest extends TestCase
{
    public function testResendWelcomeMail(): void {
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user');
        putenv('SMTP_PASS=pass');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user';
        $_ENV['SMTP_PASS'] = 'pass';
        putenv('PASSWORD_RESET_SECRET=secret');
        $_ENV['PASSWORD_RESET_SECRET'] = 'secret';

        $app = $this->getAppInstance();
        $pdo = new PDO(getenv('POSTGRES_DSN'));
        $pdo->exec("INSERT INTO tenants(uid, subdomain, imprint_email) VALUES('t1','foo','admin@example.com')");

        $twig = new Environment(new FilesystemLoader(__DIR__ . '/../../templates'));
        $mailer = new class ($twig) extends MailService {
            public array $messages = [];

            protected function createTransport(string $dsn): \Symfony\Component\Mailer\MailerInterface {
                return new class ($this) implements \Symfony\Component\Mailer\MailerInterface {
                    private $outer;

                    public function __construct($outer) {
                        $this->outer = $outer;
                    }

                    public function send(
                        \Symfony\Component\Mime\RawMessage $message,
                        ?\Symfony\Component\Mailer\Envelope $envelope = null
                    ): void {
                        $this->outer->messages[] = $message;
                    }
                };
            }
        };

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $request = $this->createRequest('POST', '/admin/profile/welcome', [
            'HTTP_X_CSRF_TOKEN' => 'token',
        ])->withUri(new Uri('http', 'foo.example.com', 80, '/admin/profile/welcome'))
          ->withAttribute('mailService', $mailer);
        $response = $app->handle($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertNotEmpty($mailer->messages);

        session_destroy();
        putenv('MAIN_DOMAIN');
        putenv('SMTP_HOST');
        putenv('SMTP_USER');
        putenv('SMTP_PASS');
        unset($_ENV['MAIN_DOMAIN'], $_ENV['SMTP_HOST'], $_ENV['SMTP_USER'], $_ENV['SMTP_PASS']);
    }
}
