<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class LandingNewsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $pdo = $this->getDatabase();
        $pdo->prepare('DELETE FROM landing_news WHERE slug = ?')->execute(['grand-opening']);
        $pdo->prepare('DELETE FROM pages WHERE slug = ?')->execute(['festival']);

        $stmt = $pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)');
        $stmt->execute(['festival', 'Festival', '<p>Festival</p>']);

        $idStmt = $pdo->prepare('SELECT id FROM pages WHERE slug = ? LIMIT 1');
        $idStmt->execute(['festival']);
        $pageId = (int) $idStmt->fetchColumn();
        if ($pageId <= 0) {
            $this->fail('Failed to create landing page for news test.');
        }

        $newsStmt = $pdo->prepare(
            'INSERT INTO landing_news (page_id, slug, title, excerpt, content, published_at, is_published) '
            . 'VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)'
        );
        $newsStmt->execute([
            $pageId,
            'grand-opening',
            'Grand Opening',
            '<p>Summary</p>',
            '<p>Full content</p>',
            1,
        ]);
    }

    public function testCustomLandingNewsArticleIsReachable(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/festival/news/grand-opening');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Grand Opening', $body);
        $this->assertStringContainsString('Festival', $body);
    }
}
