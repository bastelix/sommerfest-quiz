<?php

declare(strict_types=1);

namespace Tests\Service;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

use App\Service\MailProvider\MailProviderInterface;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\SettingsService;
use Symfony\Component\Mime\Email;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

class MailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $pdo = $this->createDatabase();
        $this->setDatabase($pdo);
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, imprint_name, imprint_email) "
            . "VALUES('main','main','Example Org','admin@example.org')"
        );

        putenv('MAILER_DSN');
        unset($_ENV['MAILER_DSN']);
        putenv('MAIL_PROVIDER_SECRET=test-secret');
        $_ENV['MAIL_PROVIDER_SECRET'] = 'test-secret';
    }

    public function testIsConfiguredTrueWhenSmtpCredentialsPresent(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        putenv('SMTP_FROM=user@example.org');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_FROM'] = 'user@example.org';

        $this->assertTrue(MailService::isConfigured());
    }

    public function testIsConfiguredFalseWhenMissingCredentials(): void
    {
        putenv('SMTP_HOST');
        putenv('SMTP_USER');
        putenv('SMTP_PASS');
        putenv('SMTP_FROM');
        unset($_ENV['SMTP_HOST'], $_ENV['SMTP_USER'], $_ENV['SMTP_PASS'], $_ENV['SMTP_FROM']);

        $this->assertFalse(MailService::isConfigured());
    }

    public function testConstructorRequiresConfiguredProvider(): void
    {
        putenv('SMTP_HOST');
        putenv('SMTP_USER');
        putenv('SMTP_PASS');
        putenv('SMTP_FROM');
        unset($_ENV['SMTP_HOST'], $_ENV['SMTP_USER'], $_ENV['SMTP_PASS'], $_ENV['SMTP_FROM']);

        $twig = new Environment(new ArrayLoader());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mail provider is not configured.');

        new MailService($twig, $this->createCollectingManager(false));
    }

    public function testSendPasswordResetDelegatesToProvider(): void
    {
        $provider = new CollectingProvider();
        $manager = $this->createCollectingManager(true, $provider);

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $service = new MailService($twig, $manager);

        $service->sendPasswordReset('user@example.org', 'https://quizrace.app/reset');

        $this->assertCount(1, $provider->sentEmails);
        $this->assertSame('user@example.org', $provider->sentEmails[0]->getTo()[0]->getAddress());
        $this->assertSame('Passwort zurücksetzen', $provider->sentEmails[0]->getSubject());
    }

    public function testSendContactSendsCopy(): void
    {
        $provider = new CollectingProvider();
        $manager = $this->createCollectingManager(true, $provider);

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $service = new MailService($twig, $manager);

        $service->sendContact(
            'team@example.org',
            'Jane',
            'jane@example.org',
            'Hello there',
            null,
            null,
            null,
            'Example Co'
        );

        $this->assertCount(2, $provider->sentEmails);
        $this->assertSame('team@example.org', $provider->sentEmails[0]->getTo()[0]->getAddress());
        $this->assertSame('Kontaktanfrage', $provider->sentEmails[0]->getSubject());
        $this->assertSame('Ihre Kontaktanfrage', $provider->sentEmails[1]->getSubject());
        $this->assertSame('jane@example.org', $provider->sentEmails[1]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Example Co', (string) $provider->sentEmails[0]->getHtmlBody());
        $this->assertStringContainsString('Example Co', (string) $provider->sentEmails[1]->getHtmlBody());
    }

    private function createCollectingManager(
        bool $configured,
        ?CollectingProvider $provider = null
    ): MailProviderManager {
        $pdo = $this->getDatabase();
        $settings = new SettingsService($pdo);
        $provider ??= new CollectingProvider($configured);

        return new MailProviderManager($settings, [
            'brevo' => static fn (array $config = []) => $provider,
        ]);
    }
}

class CollectingProvider implements MailProviderInterface
{
    /** @var Email[] */
    public array $sentEmails = [];

    /** @var array<string,mixed> */
    public array $lastOptions = [];

    private bool $configured;

    private string $from;

    /**
     * @param bool $configured
     */
    public function __construct(bool $configured = true, string $from = 'Example Org <user@example.org>')
    {
        $this->configured = $configured;
        $this->from = $from;
    }

    public function sendMail(Email $email, array $options = []): void
    {
        if (!$this->configured) {
            throw new \RuntimeException('Mail provider is not configured.');
        }
        $this->sentEmails[] = $email;
        $this->lastOptions = $options;
    }

    public function subscribe(string $email, array $data = []): void
    {
    }

    public function unsubscribe(string $email): void
    {
    }

    public function getStatus(): array
    {
        return [
            'name' => 'test',
            'configured' => $this->configured,
            'from_address' => $this->from,
            'missing' => $this->configured ? [] : ['SMTP_HOST'],
        ];
    }
}
