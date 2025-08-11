<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\MailService;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailServiceTest extends TestCase
{
    public function testUsesSmtpUserAsFrom(): void
    {
        $root = dirname(__DIR__, 2);
        $profile = $root . '/data/profile.json';
        $backup = file_get_contents($profile);
        file_put_contents($profile, json_encode([
            'imprint_name' => 'Example Org',
            'imprint_email' => 'admin@example.org',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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

        file_put_contents($profile, $backup);
    }

    public function testUsesFromOverride(): void
    {
        $root = dirname(__DIR__, 2);
        $profile = $root . '/data/profile.json';
        $backup = file_get_contents($profile);
        file_put_contents($profile, json_encode([
            'imprint_name' => 'Example Org',
            'imprint_email' => 'admin@example.org',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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

        file_put_contents($profile, $backup);

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

        $twig = new Environment(new ArrayLoader([
            'emails/contact.twig' => '',
            'emails/contact_copy.twig' => '',
        ]));

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

        $svc->sendContact('to@example.org', 'John Doe', 'john@example.org', 'Hi');

        $this->assertCount(2, $svc->messages);
        $this->assertSame('Kontaktanfrage', $svc->messages[0]->getSubject());
        $this->assertSame('to@example.org', $svc->messages[0]->getTo()[0]->getAddress());
        $this->assertSame('Ihre Kontaktanfrage', $svc->messages[1]->getSubject());
        $this->assertSame('john@example.org', $svc->messages[1]->getTo()[0]->getAddress());
    }
}
