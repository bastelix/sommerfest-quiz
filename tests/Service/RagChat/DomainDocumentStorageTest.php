<?php

declare(strict_types=1);

namespace Tests\Service\RagChat;

use App\Service\RagChat\DomainDocumentStorage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;

final class DomainDocumentStorageTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = sys_get_temp_dir() . '/domain-docs-' . bin2hex(random_bytes(4));
        mkdir($this->basePath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);
    }

    public function testStoreAndListDocuments(): void
    {
        $storage = new DomainDocumentStorage($this->basePath);
        $file = $this->createUpload('Guide.md', '## Hello world');

        $document = $storage->storeDocument('example.com', $file);

        $documents = $storage->listDocuments('example.com');

        self::assertCount(1, $documents);
        self::assertSame($document['id'], $documents[0]['id']);
        self::assertSame('Guide.md', $documents[0]['name']);
        self::assertNotSame('', $documents[0]['filename']);
    }

    public function testDeleteDocumentRemovesFile(): void
    {
        $storage = new DomainDocumentStorage($this->basePath);
        $file = $this->createUpload('Manual.md', '# Manual');
        $document = $storage->storeDocument('example.com', $file);

        $storage->deleteDocument('example.com', $document['id']);

        $documents = $storage->listDocuments('example.com');
        self::assertSame([], $documents);
    }

    public function testStoreDocumentRejectsUnsupportedExtension(): void
    {
        $storage = new DomainDocumentStorage($this->basePath);
        $file = $this->createUpload('notes.pdf', 'dummy');

        $this->expectException(InvalidArgumentException::class);
        $storage->storeDocument('example.com', $file);
    }

    private function createUpload(string $name, string $content): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($tempFile, $content);

        return new UploadedFile(
            $tempFile,
            $name,
            'text/markdown',
            strlen($content),
            UPLOAD_ERR_OK
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->removeDirectory($target);
            } elseif (is_file($target)) {
                unlink($target);
            }
        }
        rmdir($path);
    }
}

