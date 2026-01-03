<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\CmsPageWikiArticle;
use App\Service\CmsPageWikiArticleService;
use App\Service\Marketing\Wiki\EditorJsToMarkdown;
use App\Service\Marketing\Wiki\WikiPublisher;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CmsPageWikiArticleServiceTest extends TestCase
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
        $service = new CmsPageWikiArticleService($pdo, new EditorJsToMarkdown(), new WikiPublisher($this->exportDir));

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
            CmsPageWikiArticle::STATUS_PUBLISHED
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

        $service->updateStatus($article->getId(), CmsPageWikiArticle::STATUS_ARCHIVED);
        $archived = $service->getPublishedArticles($pageId, 'de');
        $this->assertCount(0, $archived);

        $service->deleteArticle($article->getId());
        $this->assertNull($service->getArticleById($article->getId()));
    }

    public function testDuplicateArticleCreatesDraftWithUniqueSlug(): void
    {
        $pdo = $this->createDatabase();
        $service = new CmsPageWikiArticleService($pdo, new EditorJsToMarkdown(), null);

        $pageId = $this->createPage($pdo, 'landing', 'Landing');
        $editorState = ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Original content.']]]];

        $original = $service->saveArticle(
            $pageId,
            'de',
            'handbuch',
            'Handbuch',
            null,
            $editorState,
            CmsPageWikiArticle::STATUS_PUBLISHED
        );

        $duplicate = $service->duplicateArticle($pageId, $original->getId());

        $this->assertSame(CmsPageWikiArticle::STATUS_DRAFT, $duplicate->getStatus());
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

    public function testSaveArticleNormalizesProvidedSlug(): void
    {
        $pdo = $this->createDatabase();
        $service = new CmsPageWikiArticleService($pdo, new EditorJsToMarkdown(), null);

        $pageId = $this->createPage($pdo, 'wissen', 'Wissen');
        $editorState = ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Inhalt']]]];

        $article = $service->saveArticle(
            $pageId,
            'de',
            ' Große Einführung 2024 ',
            'Große Einführung 2024',
            null,
            $editorState
        );

        $this->assertSame('grosse-einfuhrung-2024', $article->getSlug());

        $stmt = $pdo->prepare('SELECT slug FROM marketing_page_wiki_articles WHERE id = ?');
        $stmt->execute([$article->getId()]);
        $stored = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['slug' => 'grosse-einfuhrung-2024'], $stored);
    }

    public function testSetStartDocumentSwitchesArticles(): void
    {
        $pdo = $this->createDatabase();
        $service = new CmsPageWikiArticleService($pdo, new EditorJsToMarkdown(), null);

        $pageId = $this->createPage($pdo, 'landing', 'Landing');
        $editorState = ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Intro']]]];

        $first = $service->saveArticle(
            $pageId,
            'de',
            'intro',
            'Intro',
            null,
            $editorState,
            CmsPageWikiArticle::STATUS_PUBLISHED,
            null,
            null,
            null,
            true
        );

        $second = $service->saveArticle(
            $pageId,
            'de',
            'handbuch',
            'Handbuch',
            null,
            $editorState,
            CmsPageWikiArticle::STATUS_PUBLISHED
        );

        $this->assertTrue($first->isStartDocument());
        $this->assertFalse($second->isStartDocument());

        $updatedSecond = $service->setStartDocument($second->getId(), true);
        $this->assertTrue($updatedSecond->isStartDocument());

        $reloadedFirst = $service->getArticleById($first->getId());
        $this->assertNotNull($reloadedFirst);
        $this->assertFalse($reloadedFirst->isStartDocument());

        $published = $service->getPublishedArticles($pageId, 'de');
        $this->assertNotEmpty($published);
        $this->assertSame('handbuch', $published[0]->getSlug());
        $this->assertTrue($published[0]->isStartDocument());

        $cleared = $service->setStartDocument($second->getId(), false);
        $this->assertFalse($cleared->isStartDocument());
    }

    public function testReorderArticlesUpdatesSortIndexes(): void
    {
        $pdo = $this->createDatabase();
        $service = new CmsPageWikiArticleService($pdo, new EditorJsToMarkdown(), null);

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

    public function testMarkdownImportPreservesInlineFormatting(): void
    {
        $pdo = $this->createDatabase();
        $service = new CmsPageWikiArticleService($pdo, new EditorJsToMarkdown(), null);

        $pageId = $this->createPage($pdo, 'guide', 'Guide');
        $markdown = <<<MD
Intro with a [Quiz link](https://quizrace.example/guide) that mixes *emphasis*, **importance**, ~~legacy~~ notes, and `inline code`.

- Bullet with [Docs](https://quizrace.example/docs)
MD;

        $article = $service->saveArticleFromMarkdown(
            $pageId,
            'de',
            'imported-formatting',
            'Imported Formatting',
            $markdown
        );

        $state = $article->getEditorState();
        $this->assertNotNull($state);

        $paragraphBlock = null;
        foreach ($state['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'paragraph') {
                $paragraphBlock = $block;
                break;
            }
        }

        $this->assertNotNull($paragraphBlock);
        $paragraphText = (string) ($paragraphBlock['data']['text'] ?? '');
        $this->assertStringContainsString('<a href="https://quizrace.example/guide">Quiz link</a>', $paragraphText);
        $this->assertStringContainsString('<em>emphasis</em>', $paragraphText);
        $this->assertStringContainsString('<strong>importance</strong>', $paragraphText);
        $this->assertStringContainsString('<del>legacy</del>', $paragraphText);
        $this->assertStringContainsString('<code>inline code</code>', $paragraphText);

        $listBlock = null;
        foreach ($state['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'list') {
                $listBlock = $block;
                break;
            }
        }

        $this->assertNotNull($listBlock);
        $items = $listBlock['data']['items'] ?? [];
        $this->assertIsArray($items);
        $this->assertNotSame([], $items);
        $this->assertStringContainsString('<a href="https://quizrace.example/docs">Docs</a>', (string) ($items[0] ?? ''));

        $html = $article->getContentHtml();
        $this->assertStringContainsString('<a href="https://quizrace.example/guide">Quiz link</a>', $html);
        $this->assertStringContainsString('<em>emphasis</em>', $html);
        $this->assertStringContainsString('<strong>importance</strong>', $html);
        $this->assertStringContainsString('<del>legacy</del>', $html);
        $this->assertStringContainsString('<code>inline code</code>', $html);
    }

    private function createDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            namespace TEXT NOT NULL DEFAULT "default",
            slug TEXT,
            title TEXT,
            content TEXT,
            type TEXT,
            parent_id INTEGER,
            sort_order INTEGER NOT NULL DEFAULT 0,
            status TEXT,
            language TEXT,
            content_source TEXT,
            startpage_domain TEXT,
            is_startpage INTEGER NOT NULL DEFAULT 0
        )');
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
            is_start_document BOOLEAN NOT NULL DEFAULT FALSE,
            published_at TEXT,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(page_id, locale, slug)
        )');
        $pdo->exec('CREATE UNIQUE INDEX marketing_page_wiki_articles_start_doc_idx ON marketing_page_wiki_articles(page_id, locale) WHERE is_start_document');
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
