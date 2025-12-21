<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\EmailConfirmationService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\NewsletterSubscriptionService;
use PDO;
use PHPUnit\Framework\TestCase;

class NewsletterSubscriptionServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            <<<'SQL'
CREATE TABLE email_confirmations (
    email TEXT NOT NULL,
    token TEXT NOT NULL,
    confirmed INTEGER NOT NULL DEFAULT 0,
    expires_at TEXT NOT NULL
);
CREATE UNIQUE INDEX idx_email_confirmations_token ON email_confirmations(token);
CREATE UNIQUE INDEX idx_email_confirmations_email ON email_confirmations(email);
CREATE TABLE newsletter_subscriptions (
    namespace TEXT NOT NULL DEFAULT 'default',
    email TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    consent_requested_at TEXT NOT NULL,
    consent_confirmed_at TEXT NULL,
    unsubscribe_at TEXT NULL,
    consent_metadata TEXT NULL,
    attributes TEXT NULL,
    unsubscribe_metadata TEXT NULL
);
CREATE UNIQUE INDEX idx_newsletter_subscriptions_email ON newsletter_subscriptions(namespace, email);
SQL
        );
    }

    public function testConfirmationSubscribesContact(): void
    {
        $mailer = new class extends MailService {
            public array $sent = [];

            public function __construct()
            {
            }

            public function sendDoubleOptIn(string $to, string $link): void
            {
                $this->sent[] = [$to, $link];
            }
        };

        $manager = $this->createMock(MailProviderManager::class);
        $manager->method('isConfigured')->willReturn(true);
        $manager->expects($this->once())
            ->method('subscribe')
            ->with('user@example.com', ['FIRSTNAME' => 'Alice']);
        $manager->expects($this->never())->method('unsubscribe');

        $service = new NewsletterSubscriptionService(
            $this->pdo,
            new EmailConfirmationService($this->pdo),
            $manager,
            'default',
            $mailer
        );

        $service->requestSubscription(
            'user@example.com',
            'https://example.com/newsletter/confirm',
            ['ip' => '127.0.0.1'],
            ['FIRSTNAME' => 'Alice']
        );

        $this->assertCount(1, $mailer->sent);
        $this->assertSame('user@example.com', $mailer->sent[0][0]);

        $query = parse_url($mailer->sent[0][1], PHP_URL_QUERY) ?: '';
        parse_str($query, $params);
        $this->assertArrayHasKey('token', $params);
        $token = (string) $params['token'];

        $result = $service->confirmSubscription($token);
        $this->assertTrue($result->isSuccess());
        $this->assertSame(['ip' => '127.0.0.1'], $result->getMetadata());

        $stmt = $this->pdo->prepare(
            'SELECT status, consent_confirmed_at FROM newsletter_subscriptions WHERE namespace = :namespace AND email = :email'
        );
        $stmt->execute(['namespace' => 'default', 'email' => 'user@example.com']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame('subscribed', $row['status']);
        $this->assertNotNull($row['consent_confirmed_at']);
    }

    public function testUnsubscribePropagatesToProvider(): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO newsletter_subscriptions (namespace, email, status, consent_requested_at, consent_confirmed_at)'
            . ' VALUES (:namespace, :email, :status, :requested, :confirmed)'
        );
        $stmt->execute([
            'namespace' => 'default',
            'email' => 'user@example.com',
            'status' => 'subscribed',
            'requested' => $now,
            'confirmed' => $now,
        ]);

        $manager = $this->createMock(MailProviderManager::class);
        $manager->method('isConfigured')->willReturn(true);
        $manager->expects($this->once())
            ->method('unsubscribe')
            ->with('user@example.com');
        $manager->expects($this->never())->method('subscribe');

        $service = new NewsletterSubscriptionService(
            $this->pdo,
            new EmailConfirmationService($this->pdo),
            $manager,
            'default'
        );

        $result = $service->unsubscribe('user@example.com', ['ip' => '127.0.0.1']);
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare(
            'SELECT status, unsubscribe_at, unsubscribe_metadata FROM newsletter_subscriptions WHERE namespace = :namespace AND email = :email'
        );
        $stmt->execute(['namespace' => 'default', 'email' => 'user@example.com']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame('unsubscribed', $row['status']);
        $this->assertNotNull($row['unsubscribe_at']);
        $meta = json_decode((string) $row['unsubscribe_metadata'], true);
        $this->assertIsArray($meta);
        $this->assertSame('127.0.0.1', $meta['ip']);
    }
}
