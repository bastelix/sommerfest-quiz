<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\Admin\DomainChatKnowledgeController;
use App\Controller\Marketing\MarketingChatController;
use App\Service\RagChat\DomainDocumentStorage;
use App\Service\RagChat\DomainIndexManager;
use App\Service\RagChat\RagChatService;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\UploadedFile;
use Tests\TestCase;

use function bin2hex;
use function chdir;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function scandir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function is_dir;
use function is_file;
use function is_link;
use function rmdir;

final class DomainChatKnowledgeWorkflowTest extends TestCase
{
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

        $previousMarketing = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS=calserver.com');
        $_ENV['MARKETING_DOMAINS'] = 'calserver.com';

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
            if ($previousMarketing === false) {
                putenv('MARKETING_DOMAINS');
                unset($_ENV['MARKETING_DOMAINS']);
            } else {
                putenv('MARKETING_DOMAINS=' . $previousMarketing);
                $_ENV['MARKETING_DOMAINS'] = $previousMarketing;
            }

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

import rag_chatbot


def main() -> None:
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

        chdir(__DIR__ . '/../../public');

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
