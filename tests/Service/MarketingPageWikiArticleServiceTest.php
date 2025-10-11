<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\MarketingPageWikiArticle;
use App\Service\MarketingPageWikiArticleService;
use App\Service\Marketing\Wiki\EditorJsToMarkdown;
use App\Service\Marketing\Wiki\WikiPublisher;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MarketingPageWikiArticleServiceTest extends TestCase
{
    private string $exportDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportDir = sys_get_temp_dir() . '/wiki-export-' . uniqid();
        mkdir($this->exportDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->exportDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->exportDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($this->exportDir);
        }
        parent::tearDown();
    }

    public function testSavesAndPublishesArticle(): void
    {
        $pdo = $this->createDatabase();
        $service = new MarketingPageWikiArticleService($pdo, new EditorJsToMarkdown(), new WikiPublisher($this->exportDir));

        $pageId = $this->createPage($pdo, 'landing', 'Landing');
        $editorState = [
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => 'Willkommen bei QuizRace.']],
                ['type' => 'header', 'data' => ['text' => 'Setup', 'level' => 2]],
            ],
        ];

        $article = $service->saveArticle(
            $pageId,
            'de',
            'getting-started',
            'Erste Schritte',
            'So startest du in wenigen Minuten.',
            $editorState,
            MarketingPageWikiArticle::STATUS_PUBLISHED
        );

        $this->assertTrue($article->isPublished());
        $this->assertSame('getting-started', $article->getSlug());
        $this->assertStringContainsString('# Setup', $article->getContentMarkdown());
        $this->assertStringContainsString('<h2>Setup</h2>', $article->getContentHtml());

        $published = $service->getPublishedArticles($pageId, 'de');
        $this->assertCount(1, $published);

        $fetched = $service->findPublishedArticle($pageId, 'de', 'getting-started');
        $this->assertNotNull($fetched);
        $this->assertSame('Erste Schritte', $fetched->getTitle());

        $markdown = $service->exportMarkdown($article->getId());
        $this->assertStringContainsString('Willkommen bei QuizRace.', $markdown);

        $service->updateStatus($article->getId(), MarketingPageWikiArticle::STATUS_ARCHIVED);
        $archived = $service->getPublishedArticles($pageId, 'de');
        $this->assertCount(0, $archived);

        $service->deleteArticle($article->getId());
        $this->assertNull($service->getArticleById($article->getId()));
    }

    public function testDuplicateArticleCreatesDraftWithUniqueSlug(): void
    {
        $pdo = $this->createDatabase();
        $service = new MarketingPageWikiArticleService($pdo, new EditorJsToMarkdown(), null);

        $pageId = $this->createPage($pdo, 'landing', 'Landing');
        $editorState = ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Original content.']]]];

        $original = $service->saveArticle(
            $pageId,
            'de',
            'handbuch',
            'Handbuch',
            null,
            $editorState,
            MarketingPageWikiArticle::STATUS_PUBLISHED
        );

        $duplicate = $service->duplicateArticle($pageId, $original->getId());

        $this->assertSame(MarketingPageWikiArticle::STATUS_DRAFT, $duplicate->getStatus());
        $this->assertSame('handbuch-copy', $duplicate->getSlug());
        $this->assertSame('Handbuch', $duplicate->getTitle());
        $this->assertGreaterThan($original->getSortIndex(), $duplicate->getSortIndex());

        // Ensure slug uniqueness detection works when providing explicit slug.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Slug already exists for this locale.');
        $service->saveArticle(
            $pageId,
            'de',
            'handbuch',
            'Duplicate Slug',
            null,
            $editorState
        );
    }

    public function testReorderArticlesUpdatesSortIndexes(): void
    {
        $pdo = $this->createDatabase();
        $service = new MarketingPageWikiArticleService($pdo, new EditorJsToMarkdown(), null);

        $pageId = $this->createPage($pdo, 'landing', 'Landing');
        $editorState = ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Text']]]];

        $first = $service->saveArticle($pageId, 'de', 'eins', 'Eins', null, $editorState);
        $second = $service->saveArticle($pageId, 'de', 'zwei', 'Zwei', null, $editorState);
        $third = $service->saveArticle($pageId, 'de', 'drei', 'Drei', null, $editorState);

        $service->reorderArticles($pageId, [$third->getId(), $first->getId()]);

        $articles = $service->getArticlesForPage($pageId);
        $this->assertCount(3, $articles);
        $this->assertSame('drei', $articles[0]->getSlug());
        $this->assertSame(0, $articles[0]->getSortIndex());
        $this->assertSame('eins', $articles[1]->getSlug());
        $this->assertSame(1, $articles[1]->getSortIndex());
        $this->assertSame('zwei', $articles[2]->getSlug());
        $this->assertSame(2, $articles[2]->getSortIndex());

        $this->expectException(RuntimeException::class);
        $service->reorderArticles($pageId, [9999]);
    }

    private function createDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT, title TEXT, content TEXT)');
        $pdo->exec('CREATE TABLE marketing_page_wiki_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id INTEGER NOT NULL,
            slug TEXT NOT NULL,
            locale TEXT NOT NULL,
            title TEXT NOT NULL,
            excerpt TEXT,
            editor_json TEXT,
            content_md TEXT NOT NULL,
            content_html TEXT NOT NULL,
            status TEXT NOT NULL,
            sort_index INTEGER NOT NULL DEFAULT 0,
            published_at TEXT,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(page_id, locale, slug)
        )');
        $pdo->exec('CREATE TABLE marketing_page_wiki_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id INTEGER NOT NULL,
            editor_json TEXT,
            content_md TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            created_by TEXT
        )');

        return $pdo;
    }

    private function createPage(PDO $pdo, string $slug, string $title): int
    {
        $stmt = $pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)');
        $stmt->execute([$slug, $title, '<div></div>']);

        return (int) $pdo->lastInsertId();
    }
}
