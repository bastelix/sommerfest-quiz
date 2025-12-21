<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LandingMediaReferenceService;
use App\Service\MediaLibraryService;
use App\Service\NamespaceResolver;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

/**
 * Administration endpoints for managing uploaded media files.
 */
class AdminMediaController
{
    private MediaLibraryService $media;
    private LandingMediaReferenceService $landing;
    private const FOLDER_NONE = '__no_folder__';

    public function __construct(
        MediaLibraryService $media,
        LandingMediaReferenceService $landing
    ) {
        $this->media = $media;
        $this->landing = $landing;
    }

    /**
     * Render the media library page.
     */
    public function index(Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        $role = (string) ($_SESSION['user']['role'] ?? '');
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();

        return $view->render($response, 'admin/media.twig', [
            'csrf_token' => $csrf,
            'limits' => $this->media->getLimits(),
            'mediaLandingSlugs' => $this->landing->getLandingSlugs($namespace),
            'role' => $role,
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
        ]);
    }

    /**
     * Return a paginated list of files as JSON.
     */
    public function list(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $scope = MediaLibraryService::SCOPE_GLOBAL;
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) ($params['perPage'] ?? 20);
        $perPage = max(1, min(100, $perPage));
        $search = trim((string) ($params['search'] ?? ''));

        try {
            $files = $this->media->listFiles($scope, null);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 400);
        }

        $landingFilter = '';
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $landingData = [
            'slugs' => $this->landing->getLandingSlugs($namespace),
            'missing' => [],
            'active' => '',
        ];

        $landingReferences = $this->landing->collect($namespace);
        $landingData['slugs'] = $landingReferences['slugs'];
        $landingData['missing'] = $landingReferences['missing'];

        $map = $landingReferences['files'];
        foreach ($files as &$file) {
            $normalized = $this->landing->normalizeFilePath((string) ($file['path'] ?? $file['url'] ?? ''));
            if ($normalized !== null && isset($map[$normalized])) {
                $file['landing'] = $map[$normalized];
            }
        }
        unset($file);

        $landingFilter = trim((string) ($params['landing'] ?? ''));
        if ($landingFilter !== '') {
            $files = array_values(array_filter(
                $files,
                static function (array $file) use ($landingFilter): bool {
                    if (!isset($file['landing']) || !is_array($file['landing'])) {
                        return false;
                    }
                    foreach ($file['landing'] as $reference) {
                        if (!is_array($reference)) {
                            continue;
                        }
                        if ((string) ($reference['slug'] ?? '') === $landingFilter) {
                            return true;
                        }
                    }

                    return false;
                }
            ));
        }

        $landingData['active'] = $landingFilter;

        $availableTags = $this->collectTags($files);
        $availableFolders = $this->collectFolders($files);

        if ($search !== '') {
            $files = array_values(
                array_filter(
                    $files,
                    static fn(array $file): bool => stripos((string) $file['name'], $search) !== false
                )
            );
        }

        $rawTagFilters = $this->normalizeTags($params['tags'] ?? []);
        $activeTagFilters = array_map(
            static fn(string $tag): string => mb_strtolower($tag),
            $rawTagFilters
        );
        if ($activeTagFilters !== []) {
            $files = array_values(
                array_filter(
                    $files,
                    static function (array $file) use ($activeTagFilters): bool {
                        $fileTags = array_map(
                            static fn(string $tag): string => mb_strtolower($tag),
                            array_map(
                                'strval',
                                $file['tags'] ?? []
                            )
                        );
                        foreach ($activeTagFilters as $tag) {
                            if (!in_array($tag, $fileTags, true)) {
                                return false;
                            }
                        }

                        return true;
                    }
                )
            );
        }

        $folderParam = $params['folder'] ?? null;
        $withoutFolder = is_string($folderParam)
            && mb_strtolower(trim($folderParam)) === self::FOLDER_NONE;
        $rawFolderFilter = $withoutFolder ? null : $this->normalizeFolder($folderParam);
        if ($withoutFolder) {
            $files = array_values(
                array_filter(
                    $files,
                    static function (array $file): bool {
                        $folder = $file['folder'] ?? null;
                        return !is_string($folder) || $folder === '';
                    }
                )
            );
        } elseif ($rawFolderFilter !== null) {
            $files = array_values(
                array_filter(
                    $files,
                    static function (array $file) use ($rawFolderFilter): bool {
                        $folder = $file['folder'] ?? null;
                        if (!is_string($folder) || $folder === '') {
                            return false;
                        }
                        return mb_strtolower($folder) === $rawFolderFilter;
                    }
                )
            );
        }

        $total = count($files);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($files, $offset, $perPage);

        $payload = [
            'files' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
            'limits' => $this->media->getLimits(),
            'filters' => [
                'tags' => $availableTags,
                'folders' => $availableFolders,
                'active' => [
                    'tags' => $rawTagFilters,
                    'folder' => $withoutFolder ? self::FOLDER_NONE : $rawFolderFilter,
                    'landing' => $landingFilter,
                ],
            ],
            'landing' => $landingData,
        ];

        return $this->json($response, $payload);
    }

    /**
     * Handle file uploads.
     */
    public function upload(Request $request, Response $response): Response {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $body = [];
        }
        $scope = MediaLibraryService::SCOPE_GLOBAL;

        $files = $request->getUploadedFiles();
        if (!isset($files['file'])) {
            return $this->jsonError($response, 'missing file', 400);
        }

        $file = $files['file'];

        $metadata = $this->extractMetadata($body);
        $options = [];
        $nameOption = (string) ($body['name'] ?? '');
        if ($nameOption !== '') {
            $options['name'] = $nameOption;
        }
        if ($metadata['tagsProvided']) {
            $options['tags'] = $metadata['tags'];
        }
        if ($metadata['folderProvided']) {
            $options['folder'] = $metadata['folder'];
        }

        try {
            $stored = $this->media->uploadFile(
                $scope,
                $file,
                null,
                $options !== [] ? $options : null
            );
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 400);
        }

        $payload = [
            'file' => $stored,
            'message' => 'uploaded',
            'limits' => $this->media->getLimits(),
        ];

        return $this->json($response, $payload, 201);
    }

    /**
     * Replace an existing file with a new upload.
     */
    public function replace(Request $request, Response $response): Response {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $body = [];
        }

        $scope = MediaLibraryService::SCOPE_GLOBAL;
        $name = (string) ($body['name'] ?? '');

        if ($name === '') {
            return $this->jsonError($response, 'invalid filename', 400);
        }

        $files = $request->getUploadedFiles();
        if (!isset($files['file'])) {
            return $this->jsonError($response, 'missing file', 400);
        }

        $file = $files['file'];

        try {
            $stored = $this->media->replaceFile($scope, $name, $file, null);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 400);
        }

        return $this->json($response, [
            'file' => $stored,
            'message' => 'replaced',
            'limits' => $this->media->getLimits(),
        ]);
    }

    /**
     * Convert an existing media file to another supported format.
     */
    public function convert(Request $request, Response $response): Response {
        $data = $this->parseBody($request);
        $scope = MediaLibraryService::SCOPE_GLOBAL;
        $name = (string) ($data['name'] ?? '');

        if ($name === '') {
            return $this->jsonError($response, 'invalid filename', 400);
        }

        try {
            $file = $this->media->convertFile($scope, $name, null);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 400);
        }

        return $this->json($response, [
            'file' => $file,
            'message' => 'converted',
            'limits' => $this->media->getLimits(),
        ]);
    }

    /**
     * Rename a file.
     */
    public function rename(Request $request, Response $response): Response {
        $data = $this->parseBody($request);
        $scope = MediaLibraryService::SCOPE_GLOBAL;
        $old = (string) ($data['oldName'] ?? '');
        $new = (string) ($data['newName'] ?? '');

        if ($old === '' || $new === '') {
            return $this->jsonError($response, 'invalid filename', 400);
        }

        $metadata = $this->extractMetadata($data);
        $options = [];
        if ($metadata['tagsProvided']) {
            $options['tags'] = $metadata['tags'];
        }
        if ($metadata['folderProvided']) {
            $options['folder'] = $metadata['folder'];
        }

        try {
            $file = $this->media->renameFile(
                $scope,
                $old,
                $new,
                null,
                $options !== [] ? $options : null
            );
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 400);
        }

        return $this->json($response, [
            'file' => $file,
            'message' => 'renamed',
            'limits' => $this->media->getLimits(),
        ]);
    }

    /**
     * Delete a file from the library.
     */
    public function delete(Request $request, Response $response): Response {
        $data = $this->parseBody($request);
        $scope = MediaLibraryService::SCOPE_GLOBAL;
        $name = (string) ($data['name'] ?? '');

        if ($name === '') {
            return $this->jsonError($response, 'invalid filename', 400);
        }

        try {
            $this->media->deleteFile($scope, $name, null);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 400);
        }

        return $this->json($response, [
            'message' => 'deleted',
            'limits' => $this->media->getLimits(),
        ]);
    }

    /**
     * @param list<array<string,mixed>> $files
     * @return list<string>
     */
    private function collectTags(array $files): array {
        $tags = [];
        foreach ($files as $file) {
            $entries = $file['tags'] ?? [];
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $tag) {
                if (!is_string($tag)) {
                    continue;
                }
                $tag = trim($tag);
                if ($tag === '') {
                    continue;
                }
                $tags[mb_strtolower($tag)] = $tag;
            }
        }

        if ($tags === []) {
            return [];
        }

        ksort($tags, SORT_NATURAL);

        return array_values($tags);
    }

    /**
     * @param list<array<string,mixed>> $files
     * @return list<string>
     */
    private function collectFolders(array $files): array {
        $folders = [];
        foreach ($files as $file) {
            $folder = $file['folder'] ?? null;
            if (!is_string($folder) || $folder === '') {
                continue;
            }
            $folders[$folder] = $folder;
        }

        if ($folders === []) {
            return [];
        }

        ksort($folders, SORT_NATURAL);

        return array_values($folders);
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeTags($value): array {
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $decoded = null;
            }
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/[,;]/', $value) ?: [];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($value as $item) {
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
        if (is_array($value)) {
            $value = reset($value);
        }

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

    /**
     * @param array<string,mixed> $input
     * @return array{tagsProvided:bool,tags:list<string>,folderProvided:bool,folder:?string}
     */
    private function extractMetadata(array $input): array {
        $result = [
            'tagsProvided' => false,
            'tags' => [],
            'folderProvided' => false,
            'folder' => null,
        ];

        if (array_key_exists('tags', $input)) {
            $result['tagsProvided'] = true;
            $result['tags'] = $this->normalizeTags($input['tags']);
        }

        if (array_key_exists('folder', $input)) {
            $result['folderProvided'] = true;
            $result['folder'] = $this->normalizeFolder($input['folder']);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function json(Response $response, array $payload, int $status = 200): Response {
        try {
            $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            $response->getBody()->write('{}');
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function jsonError(Response $response, string $message, int $status): Response {
        $payload = [
            'error' => $message,
            'limits' => $this->media->getLimits(),
            'landing' => [
                'slugs' => $this->landing->getLandingSlugs(),
                'missing' => [],
                'active' => '',
            ],
        ];

        return $this->json($response, $payload, $status);
    }
    /**
     * @return array<string,mixed>
     */
    private function parseBody(Request $request): array {
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $raw = (string) $request->getBody();
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
            return [];
        }

        $data = $request->getParsedBody();
        return is_array($data) ? $data : [];
    }
}
