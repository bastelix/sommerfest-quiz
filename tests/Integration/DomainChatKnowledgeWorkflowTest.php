<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\Admin\DomainChatKnowledgeController;
use App\Controller\Marketing\CalserverChatController;
use App\Service\RagChat\DomainDocumentStorage;
use App\Service\RagChat\DomainIndexManager;
use App\Service\RagChat\RagChatService;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\UploadedFile;
use Tests\TestCase;

use function bin2hex;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function scandir;
use function sys_get_temp_dir;
use function tempnam;
use function file_exists;
use function is_dir;
use function is_file;
use function is_link;
use function unlink;
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
            $chatController = new CalserverChatController('calserver', $ragService);

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
