<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\MailService;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Infrastructure\Migrations\Migrator;
use PDO;

class MailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = $this->createDatabase();
        $this->setDatabase($pdo);
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, imprint_name, imprint_email) "
            . "VALUES('main','main','Example Org','admin@example.org')"
        );
    }

    public function testIsConfiguredTrue(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';

        $this->assertTrue(MailService::isConfigured());
    }

    /**
     * @dataProvider missingEnvProvider
     */
    public function testIsConfiguredFalseIfMissing(string $var): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';

        putenv($var);
        unset($_ENV[$var]);

        $this->assertFalse(MailService::isConfigured());
    }

    public function testUsesSmtpUserAsFrom(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_PORT=587');
        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_PORT'] = '587';
        unset($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);

        $twig = new Environment(new ArrayLoader());
        $svc = new MailService($twig);

        $ref = new \ReflectionClass($svc);
        $prop = $ref->getProperty('from');
        $prop->setAccessible(true);
        $from = $prop->getValue($svc);

        $this->assertSame('Example Org <user@example.org>', $from);
    }

    public function testUsesFromOverride(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_PORT=587');
        putenv('SMTP_FROM=support@quizrace.app');
        putenv('SMTP_FROM_NAME=QuizRace Support');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_PORT'] = '587';
        $_ENV['SMTP_FROM'] = 'support@quizrace.app';
        $_ENV['SMTP_FROM_NAME'] = 'QuizRace Support';

        $twig = new Environment(new ArrayLoader());
        $svc = new MailService($twig);

        $ref = new \ReflectionClass($svc);
        $prop = $ref->getProperty('from');
        $prop->setAccessible(true);
        $from = $prop->getValue($svc);

        $this->assertSame('QuizRace Support <support@quizrace.app>', $from);

        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        unset($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
    }

    /**
     * @dataProvider missingEnvProvider
     */
    public function testMissingEnvThrows(string $var): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_PORT=587');
        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_PORT'] = '587';
        unset($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);

        putenv($var);
        unset($_ENV[$var]);

        $twig = new Environment(new ArrayLoader());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing SMTP configuration: ' . $var);

        new MailService($twig);
    }

    public function testMissingMultipleEnvThrows(): void
    {
        putenv('SMTP_HOST');
        putenv('SMTP_USER');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_PORT=587');
        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_PORT'] = '587';
        unset($_ENV['SMTP_HOST'], $_ENV['SMTP_USER'], $_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);

        $twig = new Environment(new ArrayLoader());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing SMTP configuration: SMTP_HOST, SMTP_USER');

        new MailService($twig);
    }

    /**
     * @return array<string[]>
     */
    public function missingEnvProvider(): array
    {
        return [
            ['SMTP_HOST'],
            ['SMTP_USER'],
            ['SMTP_PASS'],
        ];
    }

    public function testDsnWithEncryption(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_PORT=587');
        putenv('SMTP_ENCRYPTION=tls');
        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_PORT'] = '587';
        $_ENV['SMTP_ENCRYPTION'] = 'tls';
        unset($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);

        $twig = new Environment(new ArrayLoader());

        $svc = new class ($twig) extends MailService {
            public string $dsn = '';

            protected function createTransport(string $dsn): MailerInterface
            {
                $this->dsn = $dsn;

                return parent::createTransport($dsn);
            }
        };

        $this->assertSame(
            'smtp://user%40example.org:secret@localhost:587?encryption=tls',
            $svc->dsn
        );

        putenv('SMTP_ENCRYPTION');
        unset($_ENV['SMTP_ENCRYPTION']);
        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        unset($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
    }

    public function testSendContactSendsCopyToUser(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_PORT=587');
        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_PORT'] = '587';
        unset($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));

        $svc = new class ($twig) extends MailService {
            /** @var Email[] */
            public array $messages = [];

            protected function createTransport(string $dsn): MailerInterface
            {
                return new class ($this) implements MailerInterface {
                    private $outer;

                    public function __construct($outer)
                    {
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

        $svc->sendContact('to@example.org', 'John Doe', 'john@example.org', 'Hi', null, null);

        $this->assertCount(2, $svc->messages);
        $this->assertSame('Kontaktanfrage', $svc->messages[0]->getSubject());
        $this->assertSame('to@example.org', $svc->messages[0]->getTo()[0]->getAddress());
        $this->assertSame('Ihre Kontaktanfrage', $svc->messages[1]->getSubject());
        $this->assertSame('john@example.org', $svc->messages[1]->getTo()[0]->getAddress());
    }

    public function testSendContactUsesTemplates(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_PORT=587');
        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_PORT'] = '587';
        unset($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));

        $svc = new class ($twig) extends MailService {
            /** @var Email[] */
            public array $messages = [];

            protected function createTransport(string $dsn): MailerInterface
            {
                return new class ($this) implements MailerInterface {
                    private $outer;

                    public function __construct($outer)
                    {
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

        $svc->sendContact(
            'to@example.org',
            'Jane Doe',
            'jane@example.org',
            "Hello <b>World</b>\nLine",
            [
                'sender_name' => 'Domain Team',
                'recipient_html' => '<p>{{ name }}</p><p>{{ message_html }}</p>',
                'recipient_text' => 'Plain: {{ message_plain }}',
                'sender_html' => '<div>{{ sender_name }}</div><div>{{ message_html }}</div>',
                'sender_text' => 'Copy: {{ message_plain }}',
            ],
            'contact@example.org'
        );

        $this->assertCount(2, $svc->messages);
        $first = $svc->messages[0];
        $this->assertSame('Domain Team', $first->getFrom()[0]->getName());
        $this->assertSame('contact@example.org', $first->getFrom()[0]->getAddress());
        $this->assertSame('Kontaktanfrage', $first->getSubject());
        $this->assertStringContainsString('<p>Jane Doe</p>', (string) $first->getHtmlBody());
        $this->assertStringContainsString('&lt;b&gt;World&lt;/b&gt;<br />' . PHP_EOL . 'Line', (string) $first->getHtmlBody());
        $this->assertSame("Plain: Hello <b>World</b>\nLine", $first->getTextBody());

        $copy = $svc->messages[1];
        $this->assertSame('Domain Team', $copy->getFrom()[0]->getName());
        $this->assertStringContainsString("Copy: Hello <b>World</b>\nLine", $copy->getTextBody());
        $this->assertStringContainsString('Domain Team', (string) $copy->getHtmlBody());
    }

    public function testSendWelcomeContainsCatalogAndPasswordLinks(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_PORT=587');
        putenv('SMTP_FROM');
        putenv('SMTP_FROM_NAME');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_PORT'] = '587';
        unset($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));

        $svc = new class ($twig) extends MailService {
            /** @var Email[] */
            public array $messages = [];

            protected function createTransport(string $dsn): MailerInterface
            {
                return new class ($this) implements MailerInterface {
                    private $outer;

                    public function __construct($outer)
                    {
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

        $html = $svc->sendWelcome(
            'user@example.org',
            'foo.example.com',
            'https://foo.example.com/password/set?token=abc'
        );

        $this->assertStringContainsString('https://foo.example.com/admin/catalogs', $html);
        $this->assertStringContainsString('https://foo.example.com/password/set?token=abc', $html);
        $this->assertCount(1, $svc->messages);
    }
}
