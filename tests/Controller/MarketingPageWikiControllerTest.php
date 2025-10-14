<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Admin\MarketingPageWikiController;
use App\Service\Marketing\Wiki\EditorJsToMarkdown;
use App\Service\Marketing\Wiki\WikiPublisher;
use App\Service\MarketingPageWikiArticleService;
use App\Service\MarketingPageWikiSettingsService;
use App\Service\PageService;
use PDO;
use PDOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;
use Slim\Psr7\UploadedFile;
use Tests\TestCase;

final class MarketingPageWikiControllerTest extends TestCase
{
    public function testUpdateSettingsAcceptsJsonPayload(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/settings', [
            'HTTP_CONTENT_TYPE' => 'application/json; charset=utf-8',
        ]);
        $stream = (new StreamFactory())->createStream(json_encode([
            'active' => true,
            'menuLabel' => 'Docs',
        ], JSON_THROW_ON_ERROR));
        $request = $request->withBody($stream);

        $response = $controller->updateSettings($request, new Response(), ['pageId' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(true, $payload['active']);
        $this->assertSame('Docs', $payload['menuLabel']);

        $stored = $pdo->query('SELECT is_active, menu_label FROM marketing_page_wiki_settings WHERE page_id = 1')
            ?->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['is_active' => 1, 'menu_label' => 'Docs'], $stored);
    }

    public function testSaveArticleAcceptsJsonPayload(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/articles', [
            'HTTP_CONTENT_TYPE' => 'application/json; charset=utf-8',
        ]);
        $stream = (new StreamFactory())->createStream(json_encode([
            'locale' => 'de',
            'slug' => 'introduction',
            'title' => 'Introduction',
            'excerpt' => 'Overview',
            'isStartDocument' => true,
            'editor' => [
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['text' => 'Welcome']],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $request = $request->withBody($stream);

        $response = $controller->saveArticle($request, new Response(), ['pageId' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('introduction', $payload['slug']);
        $this->assertSame('Introduction', $payload['title']);
        $this->assertTrue($payload['isStartDocument']);

        $row = $pdo->query('SELECT slug, title, is_start_document FROM marketing_page_wiki_articles WHERE page_id = 1')
            ?->fetch(PDO::FETCH_ASSOC);
        $this->assertSame([
            'slug' => 'introduction',
            'title' => 'Introduction',
            'is_start_document' => 1,
        ], $row);
    }

    public function testSaveArticleAcceptsMarkdownUpload(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $markdown = "# Quickstart\n\nDies ist eine Einführung.";
        $resource = fopen('php://temp', 'wb+');
        fwrite($resource, $markdown);
        rewind($resource);
        $uploaded = new UploadedFile(new Stream($resource), 'quickstart.md', 'text/markdown', strlen($markdown), UPLOAD_ERR_OK);

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/articles', [
            'HTTP_CONTENT_TYPE' => 'multipart/form-data; boundary=test'
        ]);
        $request = $request->withUploadedFiles(['markdown' => $uploaded])->withParsedBody(['locale' => 'en']);

        $response = $controller->saveArticle($request, new Response(), ['pageId' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('quickstart', $payload['slug']);
        $this->assertSame('Quickstart', $payload['title']);
        $this->assertSame('en', $payload['locale']);
        $this->assertStringContainsString('Dies ist eine Einführung', $payload['contentMarkdown']);

        $row = $pdo->query('SELECT slug, locale, title FROM marketing_page_wiki_articles WHERE page_id = 1')
            ?->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['slug' => 'quickstart', 'locale' => 'en', 'title' => 'Quickstart'], $row);
    }

    public function testSaveArticleRejectsUploadWithoutMarkdownFile(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/articles', [
            'HTTP_CONTENT_TYPE' => 'multipart/form-data; boundary=test'
        ]);

        $response = $controller->saveArticle($request, new Response(), ['pageId' => 1]);

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testSaveArticleRejectsNonMarkdownUpload(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $resource = fopen('php://temp', 'wb+');
        fwrite($resource, 'Plain text');
        rewind($resource);
        $uploaded = new UploadedFile(new Stream($resource), 'notes.txt', 'text/plain', 10, UPLOAD_ERR_OK);

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/articles', [
            'HTTP_CONTENT_TYPE' => 'multipart/form-data; boundary=test'
        ])->withUploadedFiles(['markdown' => $uploaded]);

        $response = $controller->saveArticle($request, new Response(), ['pageId' => 1]);

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $payload);
        $this->assertStringContainsString('Markdown', $payload['error']);
    }

    public function testUpdateStatusReturns400ForInvalidJson(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $article = $controller->saveArticle(
            $this->createJsonRequest([
                'locale' => 'de',
                'slug' => 'guide',
                'title' => 'Guide',
                'editor' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Step']]]],
            ]),
            new Response(),
            ['pageId' => 1]
        );

        $payload = json_decode((string) $article->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $articleId = $payload['id'];

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/articles/' . $articleId . '/status', [
            'HTTP_CONTENT_TYPE' => 'application/json',
        ]);
        $stream = (new StreamFactory())->createStream('{invalid-json');
        $request = $request->withBody($stream);

        $response = $controller->updateStatus($request, new Response(), ['articleId' => $articleId]);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUpdateStartDocumentReassignsFlag(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $firstResponse = $controller->saveArticle(
            $this->createJsonRequest([
                'locale' => 'de',
                'slug' => 'alpha',
                'title' => 'Alpha',
                'editor' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Alpha']]]],
                'isStartDocument' => true,
            ]),
            new Response(),
            ['pageId' => 1]
        );

        $firstPayload = json_decode((string) $firstResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $firstId = (int) $firstPayload['id'];

        $secondResponse = $controller->saveArticle(
            $this->createJsonRequest([
                'locale' => 'de',
                'slug' => 'beta',
                'title' => 'Beta',
                'editor' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Beta']]]],
            ]),
            new Response(),
            ['pageId' => 1]
        );

        $secondPayload = json_decode((string) $secondResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $secondId = (int) $secondPayload['id'];

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/articles/' . $secondId . '/start', [
            'HTTP_CONTENT_TYPE' => 'application/json; charset=utf-8',
        ]);
        $stream = (new StreamFactory())->createStream(json_encode(['isStartDocument' => true], JSON_THROW_ON_ERROR));
        $request = $request->withBody($stream);

        $response = $controller->updateStartDocument($request, new Response(), ['articleId' => $secondId]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($secondId, $payload['id']);
        $this->assertTrue($payload['isStartDocument']);

        $rows = $pdo->query('SELECT id, is_start_document FROM marketing_page_wiki_articles ORDER BY id ASC')
            ?->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame([
            ['id' => $firstId, 'is_start_document' => 0],
            ['id' => $secondId, 'is_start_document' => 1],
        ], $rows);
    }

    public function testDuplicateAcceptsJsonPayload(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $articleResponse = $controller->saveArticle(
            $this->createJsonRequest([
                'locale' => 'de',
                'slug' => 'guide',
                'title' => 'Guide',
                'editor' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Guide']]]],
            ]),
            new Response(),
            ['pageId' => 1]
        );

        $article = json_decode((string) $articleResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $articleId = (int) $article['id'];

        $request = $this->createJsonRequestWithPayload(
            'POST',
            '/admin/pages/1/wiki/articles/' . $articleId . '/duplicate',
            [
                'slug' => 'guide-copy',
                'title' => 'Guide Copy',
            ]
        );

        $response = $controller->duplicate(
            $request,
            new Response(),
            ['pageId' => 1, 'articleId' => $articleId]
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('guide-copy', $payload['slug']);
        $this->assertSame('Guide Copy', $payload['title']);

        $rows = $pdo->query('SELECT slug, title FROM marketing_page_wiki_articles ORDER BY id ASC')
            ?->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame([
            ['slug' => 'guide', 'title' => 'Guide'],
            ['slug' => 'guide-copy', 'title' => 'Guide Copy'],
        ], $rows);
    }

    public function testDuplicateRejectsInvalidJson(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $articleResponse = $controller->saveArticle(
            $this->createJsonRequest([
                'locale' => 'de',
                'slug' => 'guide',
                'title' => 'Guide',
                'editor' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Guide']]]],
            ]),
            new Response(),
            ['pageId' => 1]
        );

        $article = json_decode((string) $articleResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $articleId = (int) $article['id'];

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/articles/' . $articleId . '/duplicate', [
            'HTTP_CONTENT_TYPE' => 'application/json; charset=utf-8',
        ]);
        $stream = (new StreamFactory())->createStream('{invalid');
        $request = $request->withBody($stream);

        $response = $controller->duplicate(
            $request,
            new Response(),
            ['pageId' => 1, 'articleId' => $articleId]
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testSortAcceptsJsonPayload(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $first = json_decode((string) $controller->saveArticle(
            $this->createJsonRequest([
                'locale' => 'de',
                'slug' => 'alpha',
                'title' => 'Alpha',
                'editor' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Alpha']]]],
            ]),
            new Response(),
            ['pageId' => 1]
        )->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $second = json_decode((string) $controller->saveArticle(
            $this->createJsonRequest([
                'locale' => 'de',
                'slug' => 'beta',
                'title' => 'Beta',
                'editor' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Beta']]]],
            ]),
            new Response(),
            ['pageId' => 1]
        )->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $third = json_decode((string) $controller->saveArticle(
            $this->createJsonRequest([
                'locale' => 'de',
                'slug' => 'gamma',
                'title' => 'Gamma',
                'editor' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Gamma']]]],
            ]),
            new Response(),
            ['pageId' => 1]
        )->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $request = $this->createJsonRequestWithPayload(
            'POST',
            '/admin/pages/1/wiki/articles/sort',
            [
                'order' => [
                    ['id' => $third['id']],
                    ['id' => $first['id']],
                ],
            ]
        );

        $response = $controller->sort(
            $request,
            new Response(),
            ['pageId' => 1]
        );

        $this->assertSame(204, $response->getStatusCode());

        $rows = $pdo->query('SELECT id, sort_index FROM marketing_page_wiki_articles ORDER BY sort_index ASC')
            ?->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame([
            ['id' => $third['id'], 'sort_index' => 0],
            ['id' => $first['id'], 'sort_index' => 1],
            ['id' => $second['id'], 'sort_index' => 2],
        ], $rows);
    }

    public function testSortRejectsInvalidJson(): void
    {
        $pdo = $this->createWikiDatabase();
        $controller = $this->createController($pdo);

        $request = $this->createRequest('POST', '/admin/pages/1/wiki/articles/sort', [
            'HTTP_CONTENT_TYPE' => 'application/json; charset=utf-8',
        ]);
        $stream = (new StreamFactory())->createStream('{invalid-json');
        $request = $request->withBody($stream);

        $response = $controller->sort(
            $request,
            new Response(),
            ['pageId' => 1]
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    private function createWikiDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE marketing_page_wiki_settings (
            page_id INTEGER PRIMARY KEY,
            is_active INTEGER NOT NULL DEFAULT 0,
            menu_label TEXT NULL,
            updated_at TEXT NULL
        )');
        $pdo->exec('CREATE TABLE marketing_page_wiki_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id INTEGER NOT NULL,
            slug TEXT NOT NULL,
            locale TEXT NOT NULL,
            title TEXT NOT NULL,
            excerpt TEXT NULL,
            editor_json TEXT NULL,
            content_md TEXT NOT NULL,
            content_html TEXT NOT NULL,
            status TEXT NOT NULL,
            sort_index INTEGER NULL,
            is_start_document INTEGER NOT NULL DEFAULT 0,
            published_at TEXT NULL,
            updated_at TEXT NULL
        )');
        $pdo->exec('CREATE UNIQUE INDEX marketing_page_wiki_start_doc_idx ON marketing_page_wiki_articles(page_id, locale) WHERE is_start_document = 1');
        $pdo->exec('CREATE TABLE marketing_page_wiki_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id INTEGER NOT NULL,
            editor_json TEXT NULL,
            content_md TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by TEXT NULL
        )');
        $pdo->exec('CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL,
            title TEXT NOT NULL,
            content TEXT NULL
        )');
        $pdo->exec("INSERT INTO pages (id, slug, title, content) VALUES (1, 'page', 'Page', '')");

        return $pdo;
    }

    private function createController(PDO $pdo): MarketingPageWikiController
    {
        $contentRoot = $this->createPublisherRoot();

        $settingsService = new MarketingPageWikiSettingsService($pdo);
        $articleService = new MarketingPageWikiArticleService(
            $pdo,
            new EditorJsToMarkdown(),
            new WikiPublisher($contentRoot)
        );
        $pageService = new PageService($pdo);

        return new MarketingPageWikiController($settingsService, $articleService, $pageService);
    }

    private function createJsonRequest(array $payload): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->createJsonRequestWithPayload('POST', '/admin/pages/1/wiki/articles', $payload);
    }

    private function createJsonRequestWithPayload(string $method, string $path, array $payload): \Psr\Http\Message\ServerRequestInterface
    {
        $request = $this->createRequest($method, $path, [
            'HTTP_CONTENT_TYPE' => 'application/json; charset=utf-8',
        ]);
        $stream = (new StreamFactory())->createStream(json_encode($payload, JSON_THROW_ON_ERROR));

        return $request->withBody($stream);
    }

    private function createPublisherRoot(): string
    {
        $base = sys_get_temp_dir() . '/wiki_' . bin2hex(random_bytes(6));
        if (!mkdir($base) && !is_dir($base)) {
            throw new PDOException(sprintf('Failed to create temp directory: %s', $base));
        }

        register_shutdown_function(static function () use ($base): void {
            if (!is_dir($base)) {
                return;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                    continue;
                }

                unlink($file->getPathname());
            }

            rmdir($base);
        });

        return $base;
    }
}
