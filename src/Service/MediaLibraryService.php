<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function str_starts_with;

/**
 * Provides listing and file management for admin media uploads.
 */
class MediaLibraryService
{
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_EVENT = 'event';

    public const MAX_UPLOAD_SIZE = 5 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'pdf'];

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
    ];

    private const METADATA_FILE = '.media-metadata.json';

    private ConfigService $config;
    private ImageUploadService $images;

    public function __construct(ConfigService $config, ImageUploadService $images) {
        $this->config = $config;
        $this->images = $images;
    }

    /**
     * Return all files for the given scope.
     *
     * @return list<array<string, mixed>>
     */
    public function listFiles(string $scope, ?string $eventUid = null): array {
        [$dir, , $publicPath, $resolvedUid] = $this->resolveScope($scope, $eventUid);
        $metadata = $this->readMetadata($dir);

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
            if (str_starts_with($entry, '.')) {
                continue;
            }
            if ($entry === self::METADATA_FILE) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }
            $entryMeta = $metadata[$entry] ?? ['tags' => [], 'folder' => null];
            $files[] = $this->buildFileInfo($path, $publicPath, $scope, $resolvedUid, $entryMeta);
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
     * @param array{name?:string,tags?:list<string>,folder?:string|null}|null $options
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
        if (in_array($extension, ['svg', 'pdf'], true)) {
            $storedPath = $this->storeRawUpload($file, $dir, $relative, $unique, $extension);
        } else {
            $storedPath = $this->images->saveUploadedFile(
                $file,
                $relative,
                $unique,
                null,
                null,
                ImageUploadService::QUALITY_PHOTO,
                true
            );
        }

        $fileName = basename($storedPath);
        $absolutePath = $dir . DIRECTORY_SEPARATOR . $fileName;

        $metadata = $this->readMetadata($dir);
        $updateTags = is_array($options) && array_key_exists('tags', $options);
        $updateFolder = is_array($options) && array_key_exists('folder', $options);
        if ($updateTags || $updateFolder) {
            $tags = $updateTags ? $this->normalizeTags($options['tags'] ?? []) : [];
            $folder = $updateFolder ? $this->normalizeFolder($options['folder'] ?? null) : null;
            $metadata = $this->applyMetadata(
                $dir,
                $metadata,
                $fileName,
                $tags,
                $folder,
                $updateTags,
                $updateFolder,
                null
            );
        }

        $entryMeta = $metadata[$fileName] ?? ['tags' => [], 'folder' => null];

        return $this->buildFileInfo($absolutePath, $publicPath, $scope, $resolvedUid, $entryMeta);
    }

    /**
     * Replace the contents of an existing file while keeping its name and metadata.
     */
    public function replaceFile(
        string $scope,
        string $name,
        UploadedFileInterface $file,
        ?string $eventUid = null
    ): array {
        $name = $this->sanitizeExistingName($name);

        $clientName = (string) $file->getClientFilename();
        if ($clientName === '') {
            throw new RuntimeException('missing filename');
        }

        $targetExtension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($targetExtension === '') {
            throw new RuntimeException('invalid filename');
        }

        $uploadedExtension = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));
        if ($uploadedExtension === '') {
            throw new RuntimeException('missing extension');
        }

        if ($uploadedExtension !== $targetExtension) {
            throw new RuntimeException('extension mismatch');
        }

        $this->images->validate($file, self::MAX_UPLOAD_SIZE, self::ALLOWED_EXTENSIONS, self::ALLOWED_MIME_TYPES);

        [$dir, $relative, $publicPath, $resolvedUid] = $this->resolveScope($scope, $eventUid);
        $targetPath = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($targetPath)) {
            throw new RuntimeException('file not found');
        }

        $baseName = (string) pathinfo($name, PATHINFO_FILENAME);

        if (in_array($targetExtension, ['svg', 'pdf'], true)) {
            $this->storeRawUpload($file, $dir, $relative, $baseName, $targetExtension);
        } else {
            $this->images->saveUploadedFile(
                $file,
                $relative,
                $baseName,
                null,
                null,
                ImageUploadService::QUALITY_PHOTO,
                true
            );
        }

        clearstatcache(true, $targetPath);

        $metadata = $this->readMetadata($dir);
        $entryMeta = $metadata[$name] ?? ['tags' => [], 'folder' => null];

        return $this->buildFileInfo($targetPath, $publicPath, $scope, $resolvedUid, $entryMeta);
    }

    /**
     * Convert an existing raster image to WebP and store it alongside the original file.
     */
    public function convertFileToWebp(string $scope, string $name, ?string $eventUid = null): array {
        $name = $this->sanitizeExistingName($name);

        [$dir, $relative, $publicPath, $resolvedUid] = $this->resolveScope($scope, $eventUid);
        $sourcePath = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($sourcePath)) {
            throw new RuntimeException('file not found');
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, ['webp', 'svg'], true)) {
            throw new RuntimeException('unsupported conversion');
        }

        if (!in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            throw new RuntimeException('unsupported conversion');
        }

        $baseName = (string) pathinfo($name, PATHINFO_FILENAME);
        $baseName = $this->sanitizeBaseName($baseName);
        if ($baseName === '') {
            $baseName = 'image';
        }

        $targetBase = $this->uniqueBaseName($dir, $baseName, 'webp');
        $targetName = $targetBase . '.webp';

        $image = $this->images->readExistingImage($sourcePath, true);
        $this->images->saveImage(
            $image,
            $relative,
            $targetName,
            null,
            null,
            ImageUploadService::QUALITY_PHOTO,
            'webp'
        );

        $targetPath = $dir . DIRECTORY_SEPARATOR . $targetName;
        clearstatcache(true, $targetPath);

        $metadata = $this->readMetadata($dir);
        $sourceMeta = $metadata[$name] ?? null;
        if ($sourceMeta !== null) {
            $tags = array_values(array_map('strval', $sourceMeta['tags'] ?? []));
            $folderValue = $sourceMeta['folder'] ?? null;
            $folder = is_string($folderValue) && $folderValue !== '' ? $folderValue : null;
            $metadata = $this->applyMetadata(
                $dir,
                $metadata,
                $targetName,
                $tags,
                $folder,
                true,
                true,
                null
            );
        }

        $entryMeta = $metadata[$targetName] ?? ['tags' => [], 'folder' => null];

        return $this->buildFileInfo($targetPath, $publicPath, $scope, $resolvedUid, $entryMeta);
    }

    /**
     * Rename an existing file.
     */
    public function renameFile(
        string $scope,
        string $oldName,
        string $newName,
        ?string $eventUid = null,
        ?array $options = null
    ): array {
        $oldName = $this->sanitizeExistingName($oldName);
        $newName = trim($newName);
        if ($newName === '') {
            throw new RuntimeException('invalid filename');
        }

        [$dir, , $publicPath, $resolvedUid] = $this->resolveScope($scope, $eventUid);
        $metadata = $this->readMetadata($dir);

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
        $updateTags = is_array($options) && array_key_exists('tags', $options);
        $updateFolder = is_array($options) && array_key_exists('folder', $options);
        $tags = $updateTags ? $this->normalizeTags($options['tags'] ?? []) : [];
        $folder = $updateFolder ? $this->normalizeFolder($options['folder'] ?? null) : null;

        $resultPath = $oldPath;
        $resultName = $oldName;

        if (strcasecmp($oldName, $targetName) !== 0) {
            if (is_file($targetPath)) {
                throw new RuntimeException('file exists');
            }

            if (!rename($oldPath, $targetPath)) {
                throw new RuntimeException('rename failed');
            }

            unset($metadata[$oldName]);
            $resultPath = $targetPath;
            $resultName = $targetName;
        }

        $metadata = $this->applyMetadata(
            $dir,
            $metadata,
            $resultName,
            $tags,
            $folder,
            $updateTags,
            $updateFolder,
            $resultName !== $oldName ? $oldName : null
        );

        $entryMeta = $metadata[$resultName] ?? ['tags' => [], 'folder' => null];

        return $this->buildFileInfo($resultPath, $publicPath, $scope, $resolvedUid, $entryMeta);
    }

    /**
     * Delete a file within the given scope.
     */
    public function deleteFile(string $scope, string $name, ?string $eventUid = null): void {
        $name = $this->sanitizeExistingName($name);
        [$dir] = $this->resolveScope($scope, $eventUid);
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            throw new RuntimeException('file not found');
        }
        if (!@unlink($path)) {
            throw new RuntimeException('delete failed');
        }
        $metadata = $this->readMetadata($dir);
        if (isset($metadata[$name])) {
            unset($metadata[$name]);
            $this->persistMetadata($dir, $metadata);
        }
    }

    /**
     * Return static upload limits for API responses.
     *
     * @return array{maxSize:int, allowedExtensions:list<string>, allowedMimeTypes:list<string>}
     */
    public function getLimits(): array {
        return [
            'maxSize' => self::MAX_UPLOAD_SIZE,
            'allowedExtensions' => self::ALLOWED_EXTENSIONS,
            'allowedMimeTypes' => self::ALLOWED_MIME_TYPES,
        ];
    }

    /**
     * @return array{0:string,1:string,2:string,3:?string}
     */
    private function resolveScope(string $scope, ?string $eventUid): array {
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

    private function sanitizeExistingName(string $name): string {
        $name = trim($name);
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new RuntimeException('invalid filename');
        }
        if ($name === '.' || $name === '..') {
            throw new RuntimeException('invalid filename');
        }
        return $name;
    }

    private function sanitizeBaseName(string $base): string {
        $base = strtolower(trim($base));
        $base = preg_replace('/[^a-z0-9_-]+/i', '-', $base) ?? '';
        $base = trim($base, '-_');
        return $base;
    }

    private function uniqueBaseName(string $dir, string $base, string $extension): string {
        $candidate = $base;
        $suffix = 1;
        while (is_file($dir . DIRECTORY_SEPARATOR . $candidate . '.' . $extension)) {
            $candidate = $base . '-' . $suffix;
            ++$suffix;
        }

        return $candidate;
    }

    private function storeRawUpload(
        UploadedFileInterface $file,
        string $targetDir,
        string $relativeDir,
        string $baseName,
        string $extension
    ): string {
        $filename = $baseName . '.' . $extension;
        $path = $targetDir . DIRECTORY_SEPARATOR . $filename;

        $file->moveTo($path);
        @chown($path, 'www-data');
        @chgrp($path, 'www-data');
        @chmod($path, 0664);

        $relativeDir = trim($relativeDir, '/');
        $relativePath = $relativeDir !== '' ? '/' . $relativeDir . '/' . $filename : '/' . $filename;

        return $relativePath;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFileInfo(
        string $path,
        string $publicPath,
        string $scope,
        ?string $eventUid,
        ?array $metadata = null
    ): array {
        $name = basename($path);
        $size = filesize($path);
        $modified = filemtime($path) ?: time();
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $public = rtrim($publicPath, '/') . '/' . $name;

        $meta = $metadata ?? ['tags' => [], 'folder' => null];
        $tags = array_values(array_map('strval', $meta['tags'] ?? []));
        $folder = $meta['folder'] ?? null;
        $folder = $folder !== null ? (string) $folder : null;

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
            'tags' => $tags,
            'folder' => $folder,
        ];
    }

    /**
     * @return array<string, array{tags:list<string>,folder:?string}>
     */
    private function readMetadata(string $dir): array {
        $path = $dir . DIRECTORY_SEPARATOR . self::METADATA_FILE;
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $normalized = [];
        foreach ($data as $name => $meta) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            $normalized[$name] = $this->normalizeMetadataEntry(is_array($meta) ? $meta : []);
        }

        return $normalized;
    }

    /**
     * @param array<string, array{tags:list<string>,folder:?string}> $metadata
     */
    private function persistMetadata(string $dir, array $metadata): void {
        $path = $dir . DIRECTORY_SEPARATOR . self::METADATA_FILE;
        if ($metadata === []) {
            if (is_file($path)) {
                @unlink($path);
            }
            return;
        }

        $encoded = json_encode($metadata, JSON_PRETTY_PRINT);
        if ($encoded === false) {
            throw new RuntimeException('unable to encode metadata');
        }

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('unable to create metadata directory');
        }

        if (@file_put_contents($path, $encoded) === false) {
            throw new RuntimeException('unable to write metadata');
        }
    }

    /**
     * @param array<string, array{tags:list<string>,folder:?string}> $metadata
     * @return array<string, array{tags:list<string>,folder:?string}>
     */
    private function applyMetadata(
        string $dir,
        array $metadata,
        string $name,
        array $tags,
        ?string $folder,
        bool $updateTags,
        bool $updateFolder,
        ?string $oldName
    ): array {
        $current = $metadata[$name] ?? ['tags' => [], 'folder' => null];

        if ($oldName !== null && isset($metadata[$oldName])) {
            $current = $metadata[$oldName];
            unset($metadata[$oldName]);
        }

        if ($updateTags) {
            $current['tags'] = $tags;
        }
        if ($updateFolder) {
            $current['folder'] = $folder;
        }

        $current = $this->normalizeMetadataEntry($current);

        if ($current['tags'] === [] && $current['folder'] === null) {
            unset($metadata[$name]);
        } else {
            $metadata[$name] = $current;
        }

        $this->persistMetadata($dir, $metadata);

        return $metadata;
    }

    /**
     * @param array<string,mixed>|null $meta
     * @return array{tags:list<string>,folder:?string}
     */
    private function normalizeMetadataEntry(?array $meta): array {
        $meta = $meta ?? [];
        $tags = $this->normalizeTags($meta['tags'] ?? []);
        $folder = $this->normalizeFolder($meta['folder'] ?? null);

        return [
            'tags' => $tags,
            'folder' => $folder,
        ];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeTags($value): array {
        $items = [];
        if (is_string($value)) {
            $items = preg_split('/[,;]/', $value) ?: [];
        } elseif (is_array($value)) {
            $items = $value;
        } else {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            $tag = preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $item) ?? '';
            $tag = preg_replace('/\s+/', ' ', $tag) ?? '';
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            $key = mb_strtolower($tag);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = $tag;
        }

        return $normalized;
    }
    /**
     * @param mixed $value
     */
    private function normalizeFolder($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $folder = str_replace('\\', '/', trim($value));
        if ($folder === '') {
            return null;
        }

        $segments = preg_split('#/+?#', $folder) ?: [];
        $clean = [];
        foreach ($segments as $segment) {
            $segment = preg_replace('/[^\p{L}\p{N}_-]/u', '-', (string) $segment) ?? '';
            $segment = trim($segment, '-_');
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $clean[] = mb_strtolower($segment);
        }

        if ($clean === []) {
            return null;
        }

        return implode('/', $clean);
    }
}
