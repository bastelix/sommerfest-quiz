<?php

declare(strict_types=1);

namespace Tests\Controller;

use PDO;
use Tests\TestCase;

class DashboardJsonTest extends TestCase
{
    public function testAggregatesWebContentStatsPerNamespace(): void
    {
        $pdo = $this->getDatabase();
        $this->createNamespaceContentFixture($pdo);
        $app = $this->getAppInstance();

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

        $request = $this->createRequest('GET', '/admin/dashboard.json?namespace=landing');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $stats = $payload['stats'] ?? null;

        $this->assertSame([
            'pages' => 3,
            'wiki' => 2,
            'news' => 2,
            'newsletter' => 2,
            'media' => 4,
        ], $stats);
    }

    private function createNamespaceContentFixture(PDO $pdo): void
    {
        $pdo->exec("INSERT OR IGNORE INTO namespaces (namespace, label, is_active) VALUES ('landing', 'Landing', 1)");

        $pageId = $this->insertPage($pdo, 'landing', 'welcome', null, '<p><img src="/uploads/hero.jpg" alt="Hero"></p>');
        $this->insertPage($pdo, 'landing', 'about', $pageId, '<p>About us</p>');
        $this->insertPage($pdo, 'landing', 'contact', $pageId, '<p>Contact</p>');

        $this->insertWikiArticle($pdo, $pageId, 'intro');
        $this->insertWikiArticle($pdo, $pageId, 'faq');

        $this->insertLandingNews($pdo, $pageId, 'update-1', '<p><img src="/uploads/news.jpg"></p>');
        $this->insertLandingNews($pdo, $pageId, 'update-2', '<p>Update two</p>');

        $this->insertNewsletter($pdo, 'landing', 'welcome');
        $this->insertNewsletter($pdo, 'landing', 'insights');
    }

    private function insertPage(PDO $pdo, string $namespace, string $slug, ?int $parentId, string $content): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO pages (namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source, is_startpage) '
            . 'VALUES (:namespace, :slug, :title, :content, :type, :parent_id, 0, "published", "de", "manual", 0)'
        );
        $stmt->execute([
            'namespace' => $namespace,
            'slug' => $slug,
            'title' => ucfirst($slug),
            'content' => $content,
            'type' => 'landingpage',
            'parent_id' => $parentId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function insertWikiArticle(PDO $pdo, int $pageId, string $slug): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO marketing_page_wiki_articles '
            . '(page_id, slug, locale, title, excerpt, editor_json, content_md, content_html, status, sort_index, published_at, is_start_document) '
            . 'VALUES (:page_id, :slug, :locale, :title, :excerpt, :editor_json, :content_md, :content_html, :status, :sort_index, :published_at, :is_start_document)'
        );
        $stmt->execute([
            'page_id' => $pageId,
            'slug' => $slug,
            'locale' => 'de',
            'title' => ucfirst($slug),
            'excerpt' => 'Excerpt',
            'editor_json' => '{}',
            'content_md' => '# Heading',
            'content_html' => '<h1>Heading</h1>',
            'status' => 'published',
            'sort_index' => 0,
            'published_at' => '2024-01-01 00:00:00',
            'is_start_document' => 0,
        ]);
    }

    private function insertLandingNews(PDO $pdo, int $pageId, string $slug, string $content): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO landing_news (page_id, slug, title, excerpt, content, published_at, is_published, created_at, updated_at) '
            . 'VALUES (:page_id, :slug, :title, :excerpt, :content, :published_at, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'page_id' => $pageId,
            'slug' => $slug,
            'title' => ucfirst($slug),
            'excerpt' => 'Excerpt',
            'content' => $content,
            'published_at' => '2024-02-01 00:00:00',
        ]);
    }

    private function insertNewsletter(PDO $pdo, string $namespace, string $slug): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO marketing_newsletter_configs (namespace, slug, position, label, url, style) '
            . 'VALUES (:namespace, :slug, 0, :label, :url, "primary")'
        );
        $stmt->execute([
            'namespace' => $namespace,
            'slug' => $slug,
            'label' => ucfirst($slug),
            'url' => '/' . $slug,
        ]);
    }
}
