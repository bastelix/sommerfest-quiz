<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\RagChat\DomainDocumentStorage;
use App\Service\RagChat\DomainIndexManager;
use Slim\Psr7\UploadedFile;
use Tests\TestCase;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_link;
use function json_decode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class DomainChatPipelineExecutionTest extends TestCase
{
    public function testRebuildUsingPythonPipelineProducesIndex(): void
    {
        $baseDir = sys_get_temp_dir() . '/rag-domain-' . bin2hex(random_bytes(4));
        $domainsDir = $baseDir . '/domains';

        mkdir($domainsDir, 0775, true);

        $storage = new DomainDocumentStorage($domainsDir);
        $tempFile = tempnam(sys_get_temp_dir(), 'upload');
        $content = "# Domain Facts\n\nExample knowledge base.";
        file_put_contents($tempFile, $content);
        $uploaded = new UploadedFile(
            $tempFile,
            'facts.md',
            'text/markdown',
            strlen($content),
            UPLOAD_ERR_OK
        );
        $storage->storeDocument('python.example', $uploaded);

        if (is_file($tempFile)) {
            unlink($tempFile);
        }

        $projectRoot = dirname(__DIR__, 2);
        $indexManager = new DomainIndexManager($storage, $projectRoot, 'python3');

        try {
            $result = $indexManager->rebuild('python.example');

            self::assertTrue($result['success']);
            self::assertFalse($result['cleared']);

            $indexPath = $storage->getIndexPath('python.example');
            self::assertFileExists($indexPath);

            $raw = file_get_contents($indexPath);
            self::assertNotFalse($raw);
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            self::assertIsArray($payload);
            self::assertArrayHasKey('chunks', $payload);
            self::assertNotSame([], $payload['chunks']);
        } finally {
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

            $target = $path . DIRECTORY_SEPARATOR . $item;
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
