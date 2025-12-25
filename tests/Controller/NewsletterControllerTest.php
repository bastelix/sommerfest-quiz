<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\MailProvider\MailProviderManager;
use Tests\TestCase;

class NewsletterControllerTest extends TestCase
{
    public function testUnsubscribeEndpointValidatesPayload(): void
    {
        $app = $this->getAppInstance();
        $manager = $this->createMock(MailProviderManager::class);
        $manager->method('isConfigured')->willReturn(true);
        $manager->expects($this->once())
            ->method('unsubscribe')
            ->with('user@example.com');

        $request = $this->createJsonRequest('POST', '/newsletter/unsubscribe', ['email' => 'user@example.com'])
            ->withAttribute('mailProviderManager', $manager);

        $response = $app->handle($request);

        $this->assertSame(204, $response->getStatusCode());
    }
}
