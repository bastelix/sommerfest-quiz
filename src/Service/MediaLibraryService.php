<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Provides listing and file management for admin media uploads.
 */
class MediaLibraryService
{
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_EVENT = 'event';

    public const MAX_UPLOAD_SIZE = 5 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    private ConfigService $config;
    private ImageUploadService $images;

    public function __construct(ConfigService $config, ImageUploadService $images)
    {
        $this->config = $config;
        $this->images = $images;
    }

    /**
     * Return all files for the given scope.
     *
     * @return list<array<string, mixed>>
     */
    public function listFiles(string $scope, ?string $eventUid = null): array
    {
        [$dir, , $publicPath, $resolvedUid] = $this->resolveScope($scope, $eventUid);

        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $handle = opendir($dir);
        if ($handle === false) {
            return [];
        }

        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }
            $files[] = $this->buildFileInfo($path, $publicPath, $scope, $resolvedUid);
        }
        closedir($handle);

        usort(
            $files,
            static fn(array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name'])
        );

        return $files;
    }

    /**
     * Upload a file for the requested scope.
     *
     * @param array{name?:string}|null $options
     */
    public function uploadFile(
        string $scope,
        UploadedFileInterface $file,
        ?string $eventUid = null,
        ?array $options = null
    ): array {
        $clientName = (string) $file->getClientFilename();
        if ($clientName === '') {
            throw new RuntimeException('missing filename');
        }

        $extension = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));
        if ($extension === '') {
            throw new RuntimeException('missing extension');
        }

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('unsupported file type');
        }

        $this->images->validate($file, self::MAX_UPLOAD_SIZE, self::ALLOWED_EXTENSIONS, self::ALLOWED_MIME_TYPES);

        [$dir, $relative, $publicPath, $resolvedUid] = $this->resolveScope($scope, $eventUid);

        $baseName = $options['name'] ?? (string) pathinfo($clientName, PATHINFO_FILENAME);
        $baseName = $this->sanitizeBaseName($baseName);
        if ($baseName === '') {
            $baseName = 'upload';
        }

        $unique = $this->uniqueBaseName($dir, $baseName, $extension);
        $storedPath = $this->images->saveUploadedFile(
            $file,
            $relative,
            $unique,
            null,
            null,
            ImageUploadService::QUALITY_PHOTO,
            true
        );

        $fileName = basename($storedPath);
        $absolutePath = $dir . DIRECTORY_SEPARATOR . $fileName;

        return $this->buildFileInfo($absolutePath, $publicPath, $scope, $resolvedUid);
    }

    /**
     * Rename an existing file.
     */
    public function renameFile(
        string $scope,
        string $oldName,
        string $newName,
        ?string $eventUid = null
    ): array {
        $oldName = $this->sanitizeExistingName($oldName);
        $newName = trim($newName);
        if ($newName === '') {
            throw new RuntimeException('invalid filename');
        }

        [$dir, , $publicPath, $resolvedUid] = $this->resolveScope($scope, $eventUid);

        $oldPath = $dir . DIRECTORY_SEPARATOR . $oldName;
        if (!is_file($oldPath)) {
            throw new RuntimeException('file not found');
        }

        $oldExtension = strtolower((string) pathinfo($oldName, PATHINFO_EXTENSION));
        $newExtension = strtolower((string) pathinfo($newName, PATHINFO_EXTENSION));
        if ($newExtension === '') {
            $newExtension = $oldExtension;
        }

        if ($oldExtension !== $newExtension) {
            throw new RuntimeException('extension change not allowed');
        }

        $base = pathinfo($newName, PATHINFO_FILENAME);
        $base = $this->sanitizeBaseName($base);
        if ($base === '') {
            throw new RuntimeException('invalid filename');
        }

        $targetName = $base . ($newExtension !== '' ? '.' . $newExtension : '');
        $targetPath = $dir . DIRECTORY_SEPARATOR . $targetName;

        if (strcasecmp($oldName, $targetName) === 0) {
            return $this->buildFileInfo($oldPath, $publicPath, $scope, $resolvedUid);
        }

        if (is_file($targetPath)) {
            throw new RuntimeException('file exists');
        }

        if (!rename($oldPath, $targetPath)) {
            throw new RuntimeException('rename failed');
        }

        return $this->buildFileInfo($targetPath, $publicPath, $scope, $resolvedUid);
    }

    /**
     * Delete a file within the given scope.
     */
    public function deleteFile(string $scope, string $name, ?string $eventUid = null): void
    {
        $name = $this->sanitizeExistingName($name);
        [$dir] = $this->resolveScope($scope, $eventUid);
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            throw new RuntimeException('file not found');
        }
        if (!@unlink($path)) {
            throw new RuntimeException('delete failed');
        }
    }

    /**
     * Return static upload limits for API responses.
     *
     * @return array{maxSize:int, allowedExtensions:list<string>, allowedMimeTypes:list<string>}
     */
    public function getLimits(): array
    {
        return [
            'maxSize' => self::MAX_UPLOAD_SIZE,
            'allowedExtensions' => self::ALLOWED_EXTENSIONS,
            'allowedMimeTypes' => self::ALLOWED_MIME_TYPES,
        ];
    }

    /**
     * @return array{0:string,1:string,2:string,3:?string}
     */
    private function resolveScope(string $scope, ?string $eventUid): array
    {
        if ($scope === self::SCOPE_EVENT) {
            $uid = $eventUid ?? $this->config->getActiveEventUid();
            if ($uid === '') {
                throw new RuntimeException('event required');
            }
            $dir = $this->config->getEventImagesDir($uid);
            $public = $this->config->getEventImagesPath($uid);
            $relative = ltrim($public, '/');
            return [$dir, $relative, $public, $uid];
        }

        if ($scope !== self::SCOPE_GLOBAL) {
            throw new RuntimeException('invalid scope');
        }

        $dir = $this->config->getGlobalUploadsDir();
        $public = $this->config->getGlobalUploadsPath();
        $relative = ltrim($public, '/');

        return [$dir, $relative, $public, null];
    }

    private function sanitizeExistingName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new RuntimeException('invalid filename');
        }
        if ($name === '.' || $name === '..') {
            throw new RuntimeException('invalid filename');
        }
        return $name;
    }

    private function sanitizeBaseName(string $base): string
    {
        $base = strtolower(trim($base));
        $base = preg_replace('/[^a-z0-9_-]+/i', '-', $base) ?? '';
        $base = trim($base, '-_');
        return $base;
    }

    private function uniqueBaseName(string $dir, string $base, string $extension): string
    {
        $candidate = $base;
        $suffix = 1;
        while (is_file($dir . DIRECTORY_SEPARATOR . $candidate . '.' . $extension)) {
            $candidate = $base . '-' . $suffix;
            ++$suffix;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFileInfo(string $path, string $publicPath, string $scope, ?string $eventUid): array
    {
        $name = basename($path);
        $size = filesize($path);
        $modified = filemtime($path) ?: time();
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $public = rtrim($publicPath, '/') . '/' . $name;

        return [
            'name' => $name,
            'scope' => $scope,
            'eventUid' => $eventUid,
            'size' => $size !== false ? (int) $size : 0,
            'modified' => gmdate('c', $modified),
            'path' => $public,
            'url' => $public,
            'extension' => strtolower((string) pathinfo($name, PATHINFO_EXTENSION)),
            'mime' => $mime,
        ];
    }
}

