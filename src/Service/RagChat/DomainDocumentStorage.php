<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use App\Support\DomainNameHelper;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class DomainDocumentStorage
{
    public const MAX_FILE_SIZE = 1_048_576; // 1 MiB

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['md', 'markdown', 'html', 'htm', 'txt'];

    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $root = dirname(__DIR__, 3);
        $this->basePath = $basePath ?? $root . '/data/rag-chatbot/domains';
    }

    /**
     * @return list<array{id:string,name:string,filename:string,mime_type:string,size:int,uploaded_at:string,updated_at:string}>
     */
    public function listDocuments(string $domain): array
    {
        $normalized = $this->normaliseDomain($domain);
        $metadata = $this->readMetadata($normalized);
        $uploadsDir = $this->getUploadsDirectory($normalized);

        $documents = [];
        foreach ($metadata as $id => $entry) {
            $filename = $entry['filename'];
            $path = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
            $size = is_file($path) ? (int) filesize($path) : (int) $entry['size'];
            $uploadedAt = $entry['uploaded_at'];
            $updatedAt = is_file($path)
                ? date('c', (int) filemtime($path))
                : $entry['updated_at'];

            $documents[] = [
                'id' => $id,
                'name' => $entry['name'],
                'filename' => $filename,
                'mime_type' => $entry['mime_type'],
                'size' => $size,
                'uploaded_at' => $uploadedAt,
                'updated_at' => $updatedAt,
            ];
        }

        usort(
            $documents,
            static fn (array $a, array $b): int => strcmp($b['uploaded_at'], $a['uploaded_at'])
        );

        return $documents;
    }

    /**
     * @return array{id:string,name:string,filename:string,mime_type:string,size:int,uploaded_at:string,updated_at:string}
     */
    public function storeDocument(string $domain, UploadedFileInterface $file): array
    {
        $normalized = $this->normaliseDomain($domain);
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        $clientName = (string) $file->getClientFilename();
        if ($clientName === '') {
            throw new InvalidArgumentException('Missing filename.');
        }

        $extension = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Unsupported file type.');
        }

        $size = $file->getSize();
        if ($size !== null && $size > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('File exceeds the allowed size.');
        }

        $uploadsDir = $this->getUploadsDirectory($normalized);
        $this->ensureDirectory($uploadsDir);

        $documentId = bin2hex(random_bytes(8));
        $baseName = $this->sanitizeBaseName((string) pathinfo($clientName, PATHINFO_FILENAME));
        $storedName = sprintf('%s-%s.%s', $documentId, $baseName, $extension);
        $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $storedName;

        $file->moveTo($targetPath);
        clearstatcache(true, $targetPath);

        $actualSize = (int) filesize($targetPath);
        if ($actualSize > self::MAX_FILE_SIZE) {
            unlink($targetPath);
            throw new InvalidArgumentException('File exceeds the allowed size.');
        }

        $metadata = $this->readMetadata($normalized);
        $entry = [
            'name' => $clientName,
            'filename' => $storedName,
            'mime_type' => (string) $file->getClientMediaType(),
            'size' => $actualSize,
            'uploaded_at' => date('c'),
            'updated_at' => date('c'),
        ];
        $metadata[$documentId] = $entry;
        $this->writeMetadata($normalized, $metadata);

        return [
            'id' => $documentId,
            'name' => $entry['name'],
            'filename' => $entry['filename'],
            'mime_type' => $entry['mime_type'],
            'size' => $actualSize,
            'uploaded_at' => $entry['uploaded_at'],
            'updated_at' => $entry['updated_at'],
        ];
    }

    public function deleteDocument(string $domain, string $documentId): void
    {
        $normalized = $this->normaliseDomain($domain);
        $metadata = $this->readMetadata($normalized);
        if (!isset($metadata[$documentId])) {
            throw new RuntimeException('Document not found.');
        }

        $filename = $metadata[$documentId]['filename'];
        unset($metadata[$documentId]);
        $this->writeMetadata($normalized, $metadata);

        $path = $this->getUploadsDirectory($normalized) . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function getUploadsDirectory(string $domain): string
    {
        $normalized = $this->normaliseDomain($domain);
        return $this->basePath . DIRECTORY_SEPARATOR . $normalized . DIRECTORY_SEPARATOR . 'uploads';
    }

    public function getIndexPath(string $domain): string
    {
        $normalized = $this->normaliseDomain($domain);
        $this->ensureDirectory($this->basePath . DIRECTORY_SEPARATOR . $normalized);

        return $this->basePath . DIRECTORY_SEPARATOR . $normalized . DIRECTORY_SEPARATOR . 'index.json';
    }

    public function getCorpusPath(string $domain): string
    {
        $normalized = $this->normaliseDomain($domain);
        $this->ensureDirectory($this->basePath . DIRECTORY_SEPARATOR . $normalized);

        return $this->basePath . DIRECTORY_SEPARATOR . $normalized . DIRECTORY_SEPARATOR . 'corpus.jsonl';
    }

    public function getDomainDirectory(string $domain): string
    {
        $normalized = $this->normaliseDomain($domain);
        $path = $this->basePath . DIRECTORY_SEPARATOR . $normalized;
        $this->ensureDirectory($path);

        return $path;
    }

    public function getDocumentFiles(string $domain): array
    {
        $normalized = $this->normaliseDomain($domain);
        $metadata = $this->readMetadata($normalized);
        $uploadsDir = $this->getUploadsDirectory($normalized);

        $paths = [];
        foreach ($metadata as $entry) {
            $candidate = $uploadsDir . DIRECTORY_SEPARATOR . $entry['filename'];
            if (is_file($candidate)) {
                $paths[] = $candidate;
            }
        }

        return $paths;
    }

    public function removeIndex(string $domain): void
    {
        $normalized = $this->normaliseDomain($domain);
        $index = $this->basePath . DIRECTORY_SEPARATOR . $normalized . DIRECTORY_SEPARATOR . 'index.json';
        $corpus = $this->basePath . DIRECTORY_SEPARATOR . $normalized . DIRECTORY_SEPARATOR . 'corpus.jsonl';

        if (is_file($index)) {
            unlink($index);
        }
        if (is_file($corpus)) {
            unlink($corpus);
        }
    }

    public function normaliseDomain(string $domain): string
    {
        $canonical = DomainNameHelper::canonicalizeSlug($domain);
        if ($canonical === '') {
            throw new InvalidArgumentException('Invalid domain supplied.');
        }

        $aliases = DomainNameHelper::marketingAliases($domain);

        $legacy = DomainNameHelper::normalize($domain);
        if ($legacy !== '' && $legacy !== $canonical) {
            $aliases[] = $legacy;
        }

        foreach ($this->findLegacyDirectories($canonical) as $legacyDirectory) {
            $aliases[] = $legacyDirectory;
        }

        if ($aliases !== []) {
            $uniqueAliases = [];
            foreach ($aliases as $alias) {
                $alias = trim((string) $alias);
                if ($alias === '') {
                    continue;
                }

                $lower = strtolower($alias);
                if ($lower === $canonical) {
                    continue;
                }

                $uniqueAliases[$lower] = $alias;
            }

            foreach ($uniqueAliases as $alias) {
                $this->migrateLegacyDirectory($alias, $canonical);
            }
        }

        return $canonical;
    }

    /**
     * @return list<string>
     */
    private function findLegacyDirectories(string $canonical): array
    {
        if (!is_dir($this->basePath)) {
            return [];
        }

        $entries = scandir($this->basePath);
        if ($entries === false) {
            return [];
        }

        $candidates = [];
        $prefix = $canonical . '.';

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $lower = strtolower($entry);
            if ($lower === $canonical) {
                continue;
            }

            if (!str_starts_with($lower, $prefix)) {
                continue;
            }

            $path = $this->basePath . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($path)) {
                continue;
            }

            $candidates[] = $entry;
        }

        return $candidates;
    }

    private function migrateLegacyDirectory(string $legacy, string $canonical): void
    {
        $legacyPath = $this->basePath . DIRECTORY_SEPARATOR . $legacy;
        if (!is_dir($legacyPath)) {
            return;
        }

        $canonicalPath = $this->basePath . DIRECTORY_SEPARATOR . $canonical;
        $parent = dirname($canonicalPath);
        if (!is_dir($parent)) {
            $this->ensureDirectory($parent);
        }

        if (is_link($canonicalPath)) {
            return;
        }

        if (!is_dir($canonicalPath)) {
            if (rename($legacyPath, $canonicalPath)) {
                return;
            }

            if ($this->createSymlink($legacyPath, $canonicalPath)) {
                return;
            }

            $this->mirrorDirectory($legacyPath, $canonicalPath);

            return;
        }

        $this->mirrorDirectory($legacyPath, $canonicalPath);
    }

    private function createSymlink(string $target, string $link): bool
    {
        if (function_exists('symlink')) {
            try {
                return symlink($target, $link);
            } catch (\Throwable $exception) {
                return false;
            }
        }

        return false;
    }

    /**
     * @return array<string,array{name:string,filename:string,mime_type:string,size:int,uploaded_at:string,updated_at:string}>
     */
    private function readMetadata(string $normalizedDomain): array
    {
        $path = $this->getMetadataPath($normalizedDomain);
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read document metadata.');
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid document metadata.', 0, $exception);
        }

        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $id => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $result[(string) $id] = [
                'name' => (string) ($entry['name'] ?? ''),
                'filename' => (string) ($entry['filename'] ?? ''),
                'mime_type' => (string) ($entry['mime_type'] ?? ''),
                'size' => (int) ($entry['size'] ?? 0),
                'uploaded_at' => (string) ($entry['uploaded_at'] ?? date('c')),
                'updated_at' => (string) ($entry['updated_at'] ?? date('c')),
            ];
        }

        return $result;
    }

    /**
     * @param array<string,array{name:string,filename:string,mime_type:string,size:int,uploaded_at:string,updated_at:string}> $metadata
     */
    private function writeMetadata(string $normalizedDomain, array $metadata): void
    {
        $path = $this->getMetadataPath($normalizedDomain);
        $this->ensureDirectory(dirname($path));

        try {
            $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode document metadata.', 0, $exception);
        }

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write document metadata.');
        }
    }

    private function getMetadataPath(string $normalizedDomain): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $normalizedDomain . DIRECTORY_SEPARATOR . 'documents.json';
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory: %s', $path));
        }
    }

    private function mirrorDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            $this->ensureDirectory($destination);
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $origin = $source . DIRECTORY_SEPARATOR . $item;
            $target = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($origin)) {
                $originRealPath = realpath($origin);
                $targetRealPath = realpath($target);
                if ($originRealPath !== false && $originRealPath === $targetRealPath) {
                    continue;
                }
                $this->mirrorDirectory($origin, $target);
                continue;
            }

            if (is_file($target)) {
                continue;
            }

            $contents = file_get_contents($origin);
            if ($contents === false) {
                continue;
            }

            file_put_contents($target, $contents);
        }
    }

    private function sanitizeBaseName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';
        $name = trim($name, '-');

        return $name === '' ? 'document' : $name;
    }
}
