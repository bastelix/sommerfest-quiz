<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class NewsletterControllerTest extends TestCase
{
    public function testConfirmDisplaysSlugSpecificCtas(): void
    {
        $pdo = $this->getDatabase();
        $token = 'token123';
        $email = 'newsletter@example.com';

        $pdo->prepare('INSERT INTO domain_start_pages(domain, start_page) VALUES(:domain, :start_page)')
            ->execute([
                'domain' => 'calserver.test',
                'start_page' => 'calserver',
            ]);

        $pdo->prepare('INSERT INTO email_confirmations(email, token, confirmed, expires_at) VALUES(:email, :token, 0, :expires)')
            ->execute([
                'email' => $email,
                'token' => $token,
                'expires' => date('Y-m-d H:i:s', time() + 3600),
            ]);

        $pdo->prepare(
            'INSERT INTO newsletter_subscriptions('
            . 'namespace, email, status, consent_requested_at, consent_metadata, attributes'
            . ') VALUES (:namespace, :email, :status, :requested, :metadata, :attributes)'
        )->execute([
            'namespace' => 'default',
            'email' => $email,
            'status' => 'pending',
            'requested' => date('Y-m-d H:i:s'),
            'metadata' => json_encode(['landing' => 'calserver.test'], JSON_THROW_ON_ERROR),
            'attributes' => json_encode(['SOURCE' => 'marketing-test'], JSON_THROW_ON_ERROR),
        ]);

        $pdo->prepare(
            'INSERT INTO marketing_newsletter_configs (namespace, slug, position, label, url, style)'
            . ' VALUES (:namespace, :slug, :position, :label, :url, :style)'
            . ' ON CONFLICT(namespace, slug, position) DO UPDATE SET'
            . ' label = excluded.label, url = excluded.url, style = excluded.style'
        )->execute([
            'namespace' => 'default',
            'slug' => 'calserver',
            'position' => 0,
            'label' => 'CTA für Test',
            'url' => '/calserver#kontakt',
            'style' => 'primary',
        ]);

        $request = $this->createRequest('GET', '/newsletter/confirm?token=' . $token);
        $request = $request->withUri($request->getUri()->withHost('marketing.example')); // host irrelevant, metadata used

        $app = $this->getAppInstance();
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('CTA für Test', $body);
        $this->assertStringContainsString('href="/calserver#kontakt"', $body);
        $this->assertStringContainsString('href="/calserver"', $body, 'Fallback link should point to the resolved slug.');
    }
}
