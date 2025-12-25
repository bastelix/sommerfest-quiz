<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\MailProvider\MailProviderManager;
use App\Service\NewsletterSubscriptionService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NewsletterSubscriptionServiceTest extends TestCase
{
    public function testSubscribeForwardsMetadataToProvider(): void
    {
        $manager = $this->createMock(MailProviderManager::class);
        $manager->method('isConfigured')->willReturn(true);
        $manager->expects($this->once())
            ->method('subscribe')
            ->with('user@example.com', [
                'FIRSTNAME' => 'Alice',
                'META_IP' => '127.0.0.1',
                'META_SOURCE' => 'marketing-contact',
                'NEWSLETTER_NAMESPACE' => 'default',
            ]);

        $service = new NewsletterSubscriptionService($manager, 'default');

        $service->subscribe(
            'user@example.com',
            ['ip' => '127.0.0.1', 'source' => 'marketing-contact'],
            ['FIRSTNAME' => 'Alice']
        );
    }

    public function testSubscribeRejectsInvalidEmail(): void
    {
        $manager = $this->createMock(MailProviderManager::class);
        $manager->method('isConfigured')->willReturn(true);

        $service = new NewsletterSubscriptionService($manager, 'default');

        $this->expectException(RuntimeException::class);
        $service->subscribe('not-an-email');
    }

    public function testUnsubscribeForwardsToProvider(): void
    {
        $manager = $this->createMock(MailProviderManager::class);
        $manager->method('isConfigured')->willReturn(true);
        $manager->expects($this->once())
            ->method('unsubscribe')
            ->with('user@example.com');

        $service = new NewsletterSubscriptionService($manager, 'default');

        $this->assertTrue($service->unsubscribe('user@example.com', ['source' => 'unsubscribe-form']));
    }

    public function testUnsubscribeSkipsForInvalidEmail(): void
    {
        $manager = $this->createMock(MailProviderManager::class);
        $manager->expects($this->never())->method('unsubscribe');

        $service = new NewsletterSubscriptionService($manager, 'default');

        $this->assertFalse($service->unsubscribe('invalid-email'));
    }
}
