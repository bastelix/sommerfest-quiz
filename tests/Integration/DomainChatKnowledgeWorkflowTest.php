<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\Admin\DomainChatKnowledgeController;
use App\Controller\Marketing\MarketingChatController;
use App\Service\DomainService;
use App\Service\MarketingPageWikiArticleService;
use App\Service\PageService;
use App\Service\RagChat\DomainDocumentStorage;
use App\Service\RagChat\DomainIndexManager;
use App\Service\RagChat\DomainWikiSelectionService;
use App\Service\RagChat\RagChatService;
use App\Support\DomainNameHelper;
use App\Service\MarketingDomainProvider;
use PDO;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\UploadedFile;
use Tests\TestCase;

use function basename;
use function bin2hex;
use function chdir;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function getenv;
use function glob;
use function is_dir;
use function is_file;
use function is_link;
use function json_decode;
use function json_encode;
use function mkdir;
use function putenv;
use function random_bytes;
use function rmdir;
use function scandir;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class DomainChatKnowledgeWorkflowTest extends TestCase
{
    private ?MarketingDomainProvider $marketingProviderBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketingProviderBackup = DomainNameHelper::getMarketingDomainProvider();
    }

    protected function tearDown(): void
    {
        DomainNameHelper::setMarketingDomainProvider($this->marketingProviderBackup);
        parent::tearDown();
    }

    public function testLegacyMarketingDomainUploadIsAccessibleViaSlug(): void
    {
        $baseDir = sys_get_temp_dir() . '/rag-domain-' . bin2hex(random_bytes(4));
        $domainsDir = $baseDir . '/domains';
        $projectRoot = $baseDir . '/project';
        $scriptsDir = $projectRoot . '/scripts';
        $globalIndexPath = $baseDir . '/global-index.json';

        mkdir($domainsDir, 0775, true);
        mkdir($scriptsDir, 0775, true);

        $pipelineScript = <<<'PHP_SCRIPT'
<?php
declare(strict_types=1);

$uploadsDir = $argv[1] ?? '';
$corpusPath = null;
$indexPath = null;
for ($i = 2; $i < $argc; $i++) {
    if ($argv[$i] === '--corpus' && isset($argv[$i + 1])) {
        $corpusPath = $argv[++$i];
        continue;
    }
    if ($argv[$i] === '--index' && isset($argv[$i + 1])) {
        $indexPath = $argv[++$i];
        continue;
    }
}

$content = 'Calserver uptime details';
if ($uploadsDir !== '' && is_dir($uploadsDir)) {
    $items = scandir($uploadsDir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $uploadsDir . DIRECTORY_SEPARATOR . $item;
            if (is_file($path)) {
                $fileContent = file_get_contents($path);
                if ($fileContent !== false) {
                    $content = trim($fileContent);
                    break;
                }
            }
        }
    }
}

if ($corpusPath !== null) {
    file_put_contents($corpusPath, json_encode(['id' => 'chunk-1', 'text' => $content]) . PHP_EOL);
}

if ($indexPath !== null) {
    $payload = [
        'vocabulary' => ['uptime'],
        'idf' => [1.0],
        'chunks' => [[
            'id' => 'chunk-1',
            'text' => $content,
            'metadata' => ['source' => 'doc'],
            'vector' => [[0, 1.0]],
            'norm' => 1.0,
        ]],
    ];
    file_put_contents($indexPath, json_encode($payload));
}
PHP_SCRIPT;
        file_put_contents($scriptsDir . '/rag_pipeline.py', $pipelineScript);

        file_put_contents($globalIndexPath, json_encode([
            'vocabulary' => [],
            'idf' => [],
            'chunks' => [],
        ]));

        DomainNameHelper::setMarketingDomainProvider($this->createMarketingProvider(['calserver.com']));

        try {
            $storage = new DomainDocumentStorage($domainsDir);
            $indexManager = new DomainIndexManager($storage, $projectRoot, 'php');
            $controller = new DomainChatKnowledgeController($storage, $indexManager);

            $responseFactory = new ResponseFactory();

            $uploadRequest = $this->createRequest(
                'POST',
                '/admin/domain-chat/documents?domain=calserver.com',
                ['Content-Type' => 'multipart/form-data']
            );
            $tempFile = tempnam(sys_get_temp_dir(), 'upload');
            file_put_contents($tempFile, 'Calserver uptime details');
            $uploaded = new UploadedFile(
                $tempFile,
                'guide.md',
                'text/markdown',
                strlen('Calserver uptime details'),
                UPLOAD_ERR_OK
            );
            $uploadRequest = $uploadRequest->withUploadedFiles(['document' => $uploaded]);

            $uploadResponse = $controller->upload($uploadRequest, $responseFactory->createResponse());
            $payload = json_decode((string) $uploadResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(201, $uploadResponse->getStatusCode());
            self::assertArrayHasKey('document', $payload);
            self::assertDirectoryExists($domainsDir . '/calserver/uploads');

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            $rebuildRequest = $this->createRequest(
                'POST',
                '/admin/domain-chat/rebuild?domain=calserver.com',
                ['Accept' => 'application/json']
            );
            $rebuildResponse = $controller->rebuild($rebuildRequest, $responseFactory->createResponse());
            $rebuildPayload = json_decode((string) $rebuildResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(200, $rebuildResponse->getStatusCode());
            self::assertTrue($rebuildPayload['success']);

            $ragService = new RagChatService($globalIndexPath, $domainsDir, null, static fn (): array => []);
            $chatController = new MarketingChatController('calserver', $ragService);

            $chatRequest = $this->createRequest(
                'POST',
                '/calserver/chat',
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            );
            $chatRequest->getBody()->write(json_encode(['question' => 'Wie ist die uptime?']));
            $chatRequest->getBody()->rewind();

            $chatResponse = $chatController($chatRequest, $responseFactory->createResponse());
            $chatPayload = json_decode((string) $chatResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(200, $chatResponse->getStatusCode());
            self::assertSame('Wie ist die uptime?', $chatPayload['question']);
            self::assertNotSame([], $chatPayload['context']);
            self::assertSame('calserver', $chatPayload['context'][0]['metadata']['domain']);
            self::assertSame('doc', $chatPayload['context'][0]['metadata']['source']);
            self::assertDirectoryExists($domainsDir . '/calserver');
        } finally {
            $this->cleanupDirectory($baseDir);
        }
    }

    public function testUpdateWikiSelectionAcceptsPageSlug(): void
    {
        $pdo = $this->createWikiTestDatabase();
        $pageService = new PageService($pdo);
        $page = $pageService->create('calserver', 'Calserver', '<p>Calserver</p>');
        $articleId = $this->createWikiArticle($pdo, $page->getId(), 'getting-started');

        $baseDir = sys_get_temp_dir() . '/rag-domain-' . bin2hex(random_bytes(4));
        $domainsDir = $baseDir . '/domains';
        mkdir($domainsDir, 0775, true);

        $storage = new DomainDocumentStorage($domainsDir);
        $indexManager = new DomainIndexManager($storage, dirname(__DIR__, 2), 'php');
        $wikiSelection = new DomainWikiSelectionService($pdo);
        $wikiArticles = new MarketingPageWikiArticleService($pdo);
        $controller = new DomainChatKnowledgeController($storage, $indexManager, $wikiSelection, $wikiArticles, $pageService);

        $responseFactory = new ResponseFactory();
        $request = $this->createRequest('POST', '/admin/domain-chat/wiki-selection', [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        $request->getBody()->write(json_encode([
            'domain' => 'calserver',
            'articles' => [$articleId],
        ], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        try {
            $response = $controller->updateWikiSelection($request, $responseFactory->createResponse());
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(200, $response->getStatusCode());
            self::assertArrayHasKey('success', $payload);
            self::assertTrue($payload['success']);
            self::assertSame([$articleId], $wikiSelection->getSelectedArticleIds('calserver'));
            self::assertArrayHasKey('wiki', $payload);
            self::assertSame('calserver', $payload['wiki']['pageSlug']);
        } finally {
            $this->cleanupDirectory($baseDir);
        }
    }

    public function testUpdateWikiSelectionAcceptsMarketingDomain(): void
    {
        $pdo = $this->createWikiTestDatabase();
        $pageService = new PageService($pdo);
        $page = $pageService->create('calserver', 'Calserver', '<p>Calserver</p>');
        $articleId = $this->createWikiArticle($pdo, $page->getId(), 'getting-started');

        DomainNameHelper::setMarketingDomainProvider($this->createMarketingProvider(['calserver.com']));

        $baseDir = sys_get_temp_dir() . '/rag-domain-' . bin2hex(random_bytes(4));
        $domainsDir = $baseDir . '/domains';
        mkdir($domainsDir, 0775, true);

        $storage = new DomainDocumentStorage($domainsDir);
        $indexManager = new DomainIndexManager($storage, dirname(__DIR__, 2), 'php');
        $wikiSelection = new DomainWikiSelectionService($pdo);
        $wikiArticles = new MarketingPageWikiArticleService($pdo);
        $controller = new DomainChatKnowledgeController($storage, $indexManager, $wikiSelection, $wikiArticles, $pageService);

        $responseFactory = new ResponseFactory();
        $request = $this->createRequest('POST', '/admin/domain-chat/wiki-selection', [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        $request->getBody()->write(json_encode([
            'domain' => 'calserver.com',
            'articles' => [$articleId],
        ], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        try {
            $response = $controller->updateWikiSelection($request, $responseFactory->createResponse());
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(200, $response->getStatusCode());
            self::assertTrue($payload['success']);
            self::assertSame([$articleId], $wikiSelection->getSelectedArticleIds('calserver'));
            self::assertSame('calserver', $payload['wiki']['pageSlug']);
        } finally {
            $this->cleanupDirectory($baseDir);
        }
    }

    public function testRebuildFailureReturnsErrorResponse(): void
    {
        $baseDir = sys_get_temp_dir() . '/rag-domain-' . bin2hex(random_bytes(4));
        $domainsDir = $baseDir . '/domains';
        $projectRoot = $baseDir . '/project';
        $scriptsDir = $projectRoot . '/scripts';

        mkdir($domainsDir, 0775, true);
        mkdir($scriptsDir, 0775, true);

        $pipelineScript = <<<'PHP_SCRIPT'
<?php
declare(strict_types=1);

fwrite(STDERR, "Simulated pipeline failure\n");
exit(2);
PHP_SCRIPT;
        file_put_contents($scriptsDir . '/rag_pipeline.py', $pipelineScript);

        try {
            $storage = new DomainDocumentStorage($domainsDir);

            $tempFile = tempnam(sys_get_temp_dir(), 'upload');
            file_put_contents($tempFile, 'Domain knowledge');
            $uploaded = new UploadedFile(
                $tempFile,
                'guide.md',
                'text/markdown',
                strlen('Domain knowledge'),
                UPLOAD_ERR_OK
            );
            $storage->storeDocument('failure.test', $uploaded);

            if (is_file($tempFile)) {
                unlink($tempFile);
            }

            $indexManager = new DomainIndexManager($storage, $projectRoot, 'php');
            $controller = new DomainChatKnowledgeController($storage, $indexManager);
            $responseFactory = new ResponseFactory();

            $request = $this->createRequest(
                'POST',
                '/admin/domain-chat/rebuild?domain=failure.test',
                ['Accept' => 'application/json']
            );

            $response = $controller->rebuild($request, $responseFactory->createResponse());
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(422, $response->getStatusCode());
            self::assertSame('Simulated pipeline failure', $payload['error']);
        } finally {
            $this->cleanupDirectory($baseDir);
        }
    }

    public function testWikiSelectionFeedsDomainIndex(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(<<<'SQL'
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                namespace TEXT NOT NULL DEFAULT 'default',
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                type TEXT,
                parent_id INTEGER,
                sort_order INTEGER NOT NULL DEFAULT 0,
                status TEXT,
                language TEXT,
                content_source TEXT,
                startpage_domain TEXT,
                is_startpage INTEGER NOT NULL DEFAULT 0
            )
        SQL);
        $pdo->exec(<<<'SQL'
            CREATE TABLE marketing_page_wiki_articles (
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
                updated_at TEXT,
                is_start_document BOOLEAN NOT NULL DEFAULT FALSE
            )
        SQL);
        $pdo->exec(<<<'SQL'
            CREATE TABLE domain_chat_wiki_articles (
                domain TEXT NOT NULL,
                article_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(domain, article_id)
            )
        SQL);
        $this->setDatabase($pdo);

        $baseDir = sys_get_temp_dir() . '/rag-domain-' . bin2hex(random_bytes(4));
        $domainsDir = $baseDir . '/domains';
        $projectRoot = $baseDir . '/project';
        $scriptsDir = $projectRoot . '/scripts';

        mkdir($domainsDir, 0775, true);
        mkdir($scriptsDir, 0775, true);

        $pipelineScript = <<<'PHP_SCRIPT'
<?php
declare(strict_types=1);

$uploadsDir = $argv[1] ?? '';
$corpusPath = null;
$indexPath = null;
for ($i = 2; $i < $argc; $i++) {
    if ($argv[$i] === '--corpus' && isset($argv[$i + 1])) {
        $corpusPath = $argv[++$i];
        continue;
    }
    if ($argv[$i] === '--index' && isset($argv[$i + 1])) {
        $indexPath = $argv[++$i];
        continue;
    }
}

$files = [];
if ($uploadsDir !== '' && is_dir($uploadsDir)) {
    $items = glob($uploadsDir . DIRECTORY_SEPARATOR . '*') ?: [];
    sort($items);
    foreach ($items as $item) {
        if (!is_file($item)) {
            continue;
        }
        $contents = file_get_contents($item);
        if ($contents === false) {
            continue;
        }
        $files[] = basename($item) . ':' . trim($contents);
    }
}

if ($corpusPath !== null) {
    file_put_contents($corpusPath, json_encode(['files' => $files]) . PHP_EOL);
}

if ($indexPath !== null) {
    file_put_contents($indexPath, json_encode(['files' => $files]));
}
PHP_SCRIPT;
        file_put_contents($scriptsDir . '/rag_pipeline.py', $pipelineScript);

        $pageService = new PageService($pdo);
        $page = $pageService->create('calserver', 'Calserver', '<p>Calserver</p>');

        $publishedAt = '2025-03-01T12:00:00+00:00';
        $insertArticle = $pdo->prepare(<<<'SQL'
            INSERT INTO marketing_page_wiki_articles (
                page_id, slug, locale, title, excerpt, editor_json, content_md, content_html,
                status, sort_index, published_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $insertArticle->execute([
            $page->getId(),
            'getting-started',
            'de',
            'Getting Started',
            'Concise summary',
            json_encode(['time' => 0, 'blocks' => []]),
            'Detailed content body.',
            '<p>Detailed content body.</p>',
            'published',
            1,
            $publishedAt,
        ]);
        $articleId = (int) $pdo->lastInsertId();

        DomainNameHelper::setMarketingDomainProvider($this->createMarketingProvider(['calserver.com']));

        $wikiSelection = new DomainWikiSelectionService($pdo);
        $wikiSelection->replaceSelection('calserver.com', [$articleId]);

        $wikiArticles = new MarketingPageWikiArticleService($pdo);
        $storage = new DomainDocumentStorage($domainsDir);
        $indexManager = new DomainIndexManager($storage, $projectRoot, 'php', $wikiSelection, $wikiArticles);

        try {
            $result = $indexManager->rebuild('calserver.com');

            self::assertTrue($result['success']);
            self::assertArrayHasKey('stdout', $result);
            self::assertSame(false, $result['cleared']);

            $uploadsDir = $storage->getUploadsDirectory('calserver.com');
            self::assertDirectoryExists($uploadsDir);

            $wikiFiles = glob($uploadsDir . DIRECTORY_SEPARATOR . 'wiki-*.md');
            self::assertIsArray($wikiFiles);
            self::assertCount(1, $wikiFiles);

            $wikiFile = $wikiFiles[0];
            self::assertStringContainsString('wiki-', basename($wikiFile));
            $markdown = file_get_contents($wikiFile);
            self::assertIsString($markdown);
            self::assertStringContainsString('# Getting Started', $markdown);
            self::assertStringContainsString('Concise summary', $markdown);
            self::assertStringContainsString('Detailed content body.', $markdown);

            $expectedFilename = sprintf('wiki-%06d-de-getting-started.md', $articleId);
            self::assertSame($expectedFilename, basename($wikiFile));

            $indexPath = $storage->getIndexPath('calserver.com');
            self::assertFileExists($indexPath);
            $indexPayload = json_decode((string) file_get_contents($indexPath), true, 512, JSON_THROW_ON_ERROR);
            self::assertArrayHasKey('files', $indexPayload);
            self::assertCount(1, $indexPayload['files']);
            self::assertStringContainsString($expectedFilename, $indexPayload['files'][0]);
            self::assertStringContainsString('Concise summary', $indexPayload['files'][0]);
            self::assertStringContainsString('Detailed content body.', $indexPayload['files'][0]);
        } finally {
            $this->cleanupDirectory($baseDir);
        }
    }

    public function testRebuildSucceedsWhenWorkingDirectoryIsEnforced(): void
    {
        $originalCwd = getcwd();
        self::assertNotFalse($originalCwd);

        $baseDir = sys_get_temp_dir() . '/rag-domain-' . bin2hex(random_bytes(4));
        $domainsDir = $baseDir . '/domains';
        $projectRoot = $baseDir . '/project';
        $scriptsDir = $projectRoot . '/scripts';
        $ragChatbotDir = $projectRoot . '/rag_chatbot';

        mkdir($domainsDir, 0775, true);
        mkdir($scriptsDir, 0775, true);
        mkdir($ragChatbotDir, 0775, true);

        $pipelineScript = <<<'PYTHON'
import json
import sys
from pathlib import Path


def main() -> None:
    project_root = Path.cwd()
    if not (project_root / "rag_chatbot").exists():
        raise ModuleNotFoundError("No module named 'rag_chatbot'")

    if str(project_root) not in sys.path:
        sys.path.insert(0, str(project_root))

    import rag_chatbot

    uploads_dir = Path(sys.argv[1])
    args = sys.argv[2:]

    corpus_path = None
    index_path = None
    i = 0
    while i < len(args):
        if args[i] == "--corpus" and i + 1 < len(args):
            corpus_path = Path(args[i + 1])
            i += 2
            continue
        if args[i] == "--index" and i + 1 < len(args):
            index_path = Path(args[i + 1])
            i += 2
            continue
        i += 1

    if corpus_path is None or index_path is None:
        print("missing paths", file=sys.stderr)
        sys.exit(2)

    corpus_path.parent.mkdir(parents=True, exist_ok=True)
    index_path.parent.mkdir(parents=True, exist_ok=True)

    payload = {"imported": rag_chatbot.MARKER}
    corpus_path.write_text(json.dumps(payload))
    index_path.write_text(json.dumps(payload))

    print("stub pipeline finished")


if __name__ == "__main__":
    main()
PYTHON;

        $moduleStub = <<<'PYTHON'
MARKER = "from-module"
PYTHON;

        file_put_contents($scriptsDir . '/rag_pipeline.py', $pipelineScript);
        file_put_contents($ragChatbotDir . '/__init__.py', $moduleStub);

        $storage = new DomainDocumentStorage($domainsDir);
        $domain = 'importer';
        $uploadsDir = $storage->getUploadsDirectory($domain);
        mkdir($uploadsDir, 0775, true);

        $documentName = 'doc-1.txt';
        $documentPath = $uploadsDir . '/' . $documentName;
        file_put_contents($documentPath, 'Doc content');

        $metadata = [
            'doc-1' => [
                'name' => 'doc.txt',
                'filename' => $documentName,
                'mime_type' => 'text/plain',
                'size' => strlen('Doc content'),
                'uploaded_at' => date('c'),
                'updated_at' => date('c'),
            ],
        ];

        file_put_contents(
            dirname($uploadsDir) . '/documents.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        $corpusPath = $storage->getCorpusPath($domain);
        $indexPath = $storage->getIndexPath($domain);

        chdir(dirname(__DIR__, 2) . '/public');

        try {
            $manualResult = \App\runSyncProcess(
                'python3',
                [
                    $scriptsDir . '/rag_pipeline.py',
                    $uploadsDir,
                    '--corpus',
                    $corpusPath,
                    '--index',
                    $indexPath,
                    '--force',
                ]
            );

            self::assertFalse($manualResult['success']);
            $output = $manualResult['stderr'] !== '' ? $manualResult['stderr'] : $manualResult['stdout'];
            self::assertStringContainsString('rag_chatbot', $output);

            $manager = new DomainIndexManager($storage, $projectRoot, 'python3');
            $result = $manager->rebuild($domain);

            self::assertTrue($result['success']);
            self::assertFileExists($corpusPath);
            self::assertFileExists($indexPath);
            self::assertStringContainsString(
                'from-module',
                (string) file_get_contents($corpusPath)
            );
            self::assertStringContainsString('stub pipeline finished', $result['stdout']);
        } finally {
            chdir($originalCwd);
            $this->cleanupDirectory($baseDir);
        }
    }

    private function createWikiArticle(PDO $pdo, int $pageId, string $slug): int
    {
        $insertArticle = $pdo->prepare(<<<'SQL'
            INSERT INTO marketing_page_wiki_articles (
                page_id, slug, locale, title, excerpt, editor_json, content_md, content_html,
                status, sort_index, published_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $insertArticle->execute([
            $pageId,
            $slug,
            'de',
            'Article ' . $slug,
            'Excerpt ' . $slug,
            json_encode(['time' => 0, 'blocks' => []], JSON_THROW_ON_ERROR),
            'Content for ' . $slug,
            '<p>Content for ' . $slug . '</p>',
            'published',
            1,
            '2024-01-01T00:00:00+00:00',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param list<string> $domains
     */
    private function createMarketingProvider(array $domains): MarketingDomainProvider
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS domains ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'host TEXT NOT NULL, '
            . 'normalized_host TEXT NOT NULL UNIQUE, '
            . 'namespace TEXT, '
            . 'label TEXT, '
            . 'is_active BOOLEAN NOT NULL DEFAULT TRUE, '
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
        );

        $service = new DomainService($pdo);
        foreach ($domains as $domain) {
            $service->createDomain($domain);
        }

        return new MarketingDomainProvider(static fn (): PDO => $pdo, 0);
    }

    private function createWikiTestDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $pdo->exec(<<<'SQL'
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                namespace TEXT NOT NULL DEFAULT 'default',
                slug TEXT UNIQUE NOT NULL,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                type TEXT,
                parent_id INTEGER,
                sort_order INTEGER NOT NULL DEFAULT 0,
                status TEXT,
                language TEXT,
                content_source TEXT,
                startpage_domain TEXT,
                is_startpage INTEGER NOT NULL DEFAULT 0
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE marketing_page_wiki_articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_id INTEGER NOT NULL,
                slug TEXT NOT NULL,
                locale TEXT NOT NULL DEFAULT 'de',
                title TEXT NOT NULL,
                excerpt TEXT,
                editor_json TEXT,
                content_md TEXT NOT NULL,
                content_html TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'draft',
                sort_index INTEGER NOT NULL DEFAULT 0,
                published_at TEXT,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                is_start_document INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE domain_chat_wiki_articles (
                domain TEXT NOT NULL,
                article_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(domain, article_id),
                FOREIGN KEY (article_id) REFERENCES marketing_page_wiki_articles(id) ON DELETE CASCADE
            )
        SQL);

        return $pdo;
    }

    private function cleanupDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . '/' . $item;
            if (is_dir($target)) {
                $this->cleanupDirectory($target);
                continue;
            }

            if (is_file($target) || is_link($target)) {
                unlink($target);
            }
        }

        rmdir($path);
    }
}
