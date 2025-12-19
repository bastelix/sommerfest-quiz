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

    public function testProtocolPrefixedDomainsNormaliseToSameValue(): void
    {
        $storage = new DomainDocumentStorage($this->basePath);

        $indexPath = $storage->getIndexPath('https://www.CalServer.de/path');
        $expectedIndexPath = $this->basePath . DIRECTORY_SEPARATOR . 'calserver.de' . DIRECTORY_SEPARATOR . 'index.json';

        self::assertSame($expectedIndexPath, $indexPath);
        self::assertDirectoryExists(dirname($indexPath));

        $document = $storage->storeDocument('https://calserver.de', $this->createUpload('Guide.md', '## Hello'));
        $documents = $storage->listDocuments('calserver.de');

        self::assertCount(1, $documents);
        self::assertSame($document['id'], $documents[0]['id']);
    }

    public function testMarketingDomainIsCanonicalisedToSlug(): void
    {
        $previous = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS=calserver.com');
        $_ENV['MARKETING_DOMAINS'] = 'calserver.com';

        try {
            $storage = new DomainDocumentStorage($this->basePath);
            $file = $this->createUpload('Guide.md', '## Calserver uptime');

            $storage->storeDocument('calserver.com', $file);

            $documents = $storage->listDocuments('calserver');

            self::assertCount(1, $documents);
            self::assertDirectoryExists($this->basePath . DIRECTORY_SEPARATOR . 'calserver');
        } finally {
            if ($previous === false) {
                putenv('MARKETING_DOMAINS');
                unset($_ENV['MARKETING_DOMAINS']);
            } else {
                putenv('MARKETING_DOMAINS=' . $previous);
                $_ENV['MARKETING_DOMAINS'] = $previous;
            }
        }
    }

    public function testLegacyMarketingDirectoryMigratesToSlug(): void
    {
        $previous = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS=calserver.com');
        $_ENV['MARKETING_DOMAINS'] = 'calserver.com';

        $legacyDir = $this->basePath . DIRECTORY_SEPARATOR . 'calserver.com';
        $uploadsDir = $legacyDir . DIRECTORY_SEPARATOR . 'uploads';
        mkdir($uploadsDir, 0775, true);

        $documentId = 'legacy1234';
        file_put_contents($uploadsDir . DIRECTORY_SEPARATOR . $documentId . '-guide.md', '## Legacy content');

        $metadata = [
            $documentId => [
                'name' => 'Guide.md',
                'filename' => $documentId . '-guide.md',
                'mime_type' => 'text/markdown',
                'size' => 16,
                'uploaded_at' => date('c'),
                'updated_at' => date('c'),
            ],
        ];
        file_put_contents(
            $legacyDir . DIRECTORY_SEPARATOR . 'documents.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        try {
            $storage = new DomainDocumentStorage($this->basePath);

            $documents = $storage->listDocuments('calserver.com');

            self::assertCount(1, $documents);
            self::assertSame('Guide.md', $documents[0]['name']);
            self::assertDirectoryExists($this->basePath . DIRECTORY_SEPARATOR . 'calserver');

            $documentsBySlug = $storage->listDocuments('calserver');
            self::assertCount(1, $documentsBySlug);
        } finally {
            if ($previous === false) {
                putenv('MARKETING_DOMAINS');
                unset($_ENV['MARKETING_DOMAINS']);
            } else {
                putenv('MARKETING_DOMAINS=' . $previous);
                $_ENV['MARKETING_DOMAINS'] = $previous;
            }
        }
    }

    public function testSlugLookupMigratesLegacyMarketingDirectory(): void
    {
        $previous = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS=calserver.com');
        $_ENV['MARKETING_DOMAINS'] = 'calserver.com';

        $legacyDir = $this->basePath . DIRECTORY_SEPARATOR . 'calserver.com';
        $uploadsDir = $legacyDir . DIRECTORY_SEPARATOR . 'uploads';
        mkdir($uploadsDir, 0775, true);

        $documentId = 'legacy4321';
        file_put_contents($uploadsDir . DIRECTORY_SEPARATOR . $documentId . '-guide.md', '## Legacy slug content');

        $metadata = [
            $documentId => [
                'name' => 'Guide.md',
                'filename' => $documentId . '-guide.md',
                'mime_type' => 'text/markdown',
                'size' => 22,
                'uploaded_at' => date('c'),
                'updated_at' => date('c'),
            ],
        ];

        file_put_contents(
            $legacyDir . DIRECTORY_SEPARATOR . 'documents.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        try {
            $storage = new DomainDocumentStorage($this->basePath);

            $documents = $storage->listDocuments('calserver');

            self::assertCount(1, $documents);
            self::assertSame('Guide.md', $documents[0]['name']);
            self::assertDirectoryExists($this->basePath . DIRECTORY_SEPARATOR . 'calserver');
        } finally {
            if ($previous === false) {
                putenv('MARKETING_DOMAINS');
                unset($_ENV['MARKETING_DOMAINS']);
            } else {
                putenv('MARKETING_DOMAINS=' . $previous);
                $_ENV['MARKETING_DOMAINS'] = $previous;
            }
        }
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
