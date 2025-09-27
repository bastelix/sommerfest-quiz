<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\LandingMediaReferenceService;
use App\Service\MediaLibraryService;
use JsonException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;
use function is_array;

/**
 * Administration endpoints for managing uploaded media files.
 */
class AdminMediaController
{
    private MediaLibraryService $media;
    private ConfigService $config;
    private LandingMediaReferenceService $landingReferences;
    private const FOLDER_NONE = '__no_folder__';

    public function __construct(
        MediaLibraryService $media,
        ConfigService $config,
        LandingMediaReferenceService $landingReferences
    )
    {
        $this->media = $media;
        $this->config = $config;
        $this->landingReferences = $landingReferences;
    }

    /**
     * Render the media library page.
     */
    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        $eventUid = (string) ($_SESSION['event_uid'] ?? '');
        if ($eventUid === '') {
            $eventUid = $this->config->getActiveEventUid();
        }

        $role = (string) ($_SESSION['user']['role'] ?? '');

        return $view->render($response, 'admin/media.twig', [
            'csrf_token' => $csrf,
            'eventUid' => $eventUid,
            'limits' => $this->media->getLimits(),
            'role' => $role,
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'landingSlugs' => $this->landingReferences->getAvailableSlugs(),
        ]);
    }

    /**
     * Return a paginated list of files as JSON.
     */
    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $scope = (string) ($params['scope'] ?? MediaLibraryService::SCOPE_GLOBAL);
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) ($params['perPage'] ?? 20);
        $perPage = max(1, min(100, $perPage));
        $search = trim((string) ($params['search'] ?? ''));
        $landingSlug = trim((string) ($params['landing'] ?? ''));

        $eventUid = (string) ($params['event'] ?? '');
        if ($eventUid === '') {
            $eventUid = (string) ($_SESSION['event_uid'] ?? $this->config->getActiveEventUid());
        }

        try {
            $files = $this->media->listFiles($scope, $eventUid !== '' ? $eventUid : null);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 400);
        }

        $availableTags = $this->collectTags($files);
        $availableFolders = $this->collectFolders($files);

        if ($search !== '') {
            $files = array_values(array_filter(
                $files,
                static fn(array $file): bool => stripos((string) $file['name'], $search) !== false
            ));
        }

        $rawTagFilters = $this->normalizeTags($params['tags'] ?? []);
        $activeTagFilters = array_map(static fn(string $tag): string => mb_strtolower($tag), $rawTagFilters);
        if ($activeTagFilters !== []) {
            $files = array_values(array_filter(
                $files,
                static function (array $file) use ($activeTagFilters): bool {
                    $fileTags = array_map(static fn(string $tag): string => mb_strtolower($tag),
                        array_map('strval', $file['tags'] ?? [])
                    );
                    foreach ($activeTagFilters as $tag) {
                        if (!in_array($tag, $fileTags, true)) {
                            return false;
                        }
                    }

                    return true;
                }
            ));
        }

        $folderParam = $params['folder'] ?? null;
        $withoutFolder = is_string($folderParam)
            && mb_strtolower(trim($folderParam)) === self::FOLDER_NONE;
        $rawFolderFilter = $withoutFolder ? null : $this->normalizeFolder($folderParam);
        if ($withoutFolder) {
            $files = array_values(array_filter(
                $files,
                static function (array $file): bool {
                    $folder = $file['folder'] ?? null;
                    return !is_string($folder) || $folder === '';
                }
            ));
        } elseif ($rawFolderFilter !== null) {
            $files = array_values(array_filter(
                $files,
                static function (array $file) use ($rawFolderFilter): bool {
                    $folder = $file['folder'] ?? null;
                    if (!is_string($folder) || $folder === '') {
                        return false;
                    }
                    return mb_strtolower($folder) === $rawFolderFilter;
                }
            ));
        }

        $landingData = null;
        if ($landingSlug !== '') {
            try {
                $references = $this->landingReferences->getReferences($landingSlug);
            } catch (InvalidArgumentException $exception) {
                return $this->jsonError($response, $exception->getMessage(), 400);
            }

            $items = $this->mergeLandingReferences(
                $files,
                $references,
                $scope,
                $eventUid !== '' ? $eventUid : null
            );
            $items = $this->filterBySearch($items, $search);
            $items = $this->filterByTags($items, $activeTagFilters);
            $items = $this->filterByFolder($items, $withoutFolder, $rawFolderFilter);

            $total = count($items);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;
            $items = array_slice($items, $offset, $perPage);

            $landingData = [
                'slug' => $landingSlug,
                'totalReferences' => count($references),
                'missing' => array_reduce(
                    $references,
                    static fn (int $carry, array $reference): int => $carry + ((bool) ($reference['missing'] ?? false) ? 1 : 0),
                    0
                ),
            ];
        } else {
            $total = count($files);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;
            $items = array_slice($files, $offset, $perPage);
        }

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
                ],
            ],
        ];

        if ($landingData !== null) {
            $payload['landing'] = $landingData;
        }

        return $this->json($response, $payload);
    }

    /**
     * @param list<array<string,mixed>> $files
     * @param list<array{path:string,relativePath:string,sources:list<string>,missing:bool}> $references
     * @return list<array<string,mixed>>
     */
    private function mergeLandingReferences(
        array $files,
        array $references,
        string $scope,
        ?string $eventUid
    ): array {
        $filesByPath = [];
        foreach ($files as $file) {
            $path = isset($file['path']) ? (string) $file['path'] : '';
            if ($path === '') {
                continue;
            }
            $filesByPath[$path] = $file;
        }

        $items = [];
        foreach ($references as $reference) {
            $path = isset($reference['path']) ? (string) $reference['path'] : '';
            if ($path === '') {
                continue;
            }

            $landing = $reference;
            $exists = isset($filesByPath[$path]);
            $landing['missing'] = !$exists;

            if ($exists) {
                $file = $filesByPath[$path];
                $file['missing'] = false;
                $file['landing'] = $landing;
            } else {
                $file = $this->buildMissingLandingEntry($landing, $scope, $eventUid);
            }

            $items[] = $file;
        }

        return $items;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function filterBySearch(array $items, string $search): array
    {
        if ($search === '') {
            return $items;
        }

        $needle = mb_strtolower($search);

        return array_values(array_filter(
            $items,
            static function (array $file) use ($needle): bool {
                $candidates = [
                    isset($file['name']) ? (string) $file['name'] : '',
                    isset($file['path']) ? (string) $file['path'] : '',
                ];
                $landing = $file['landing'] ?? [];
                if (is_array($landing)) {
                    $candidates[] = isset($landing['relativePath'])
                        ? (string) $landing['relativePath']
                        : '';
                }

                foreach ($candidates as $candidate) {
                    if ($candidate !== '' && mb_stripos($candidate, $needle) !== false) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<string> $activeTagFilters
     * @return list<array<string,mixed>>
     */
    private function filterByTags(array $items, array $activeTagFilters): array
    {
        if ($activeTagFilters === []) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static function (array $file) use ($activeTagFilters): bool {
                $fileTags = array_map(
                    static fn (string $tag): string => mb_strtolower($tag),
                    array_map('strval', $file['tags'] ?? [])
                );
                foreach ($activeTagFilters as $tag) {
                    if (!in_array($tag, $fileTags, true)) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function filterByFolder(array $items, bool $withoutFolder, ?string $rawFolderFilter): array
    {
        if ($withoutFolder) {
            return array_values(array_filter(
                $items,
                static function (array $file): bool {
                    $folder = $file['folder'] ?? null;
                    return !is_string($folder) || $folder === '';
                }
            ));
        }

        if ($rawFolderFilter === null) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static function (array $file) use ($rawFolderFilter): bool {
                $folder = $file['folder'] ?? null;
                if (!is_string($folder) || $folder === '') {
                    return false;
                }

                return mb_strtolower($folder) === $rawFolderFilter;
            }
        ));
    }

    /**
     * @param array{path:string,relativePath:string,sources:list<string>,missing:bool} $reference
     * @return array<string,mixed>
     */
    private function buildMissingLandingEntry(array $reference, string $scope, ?string $eventUid): array
    {
        $path = (string) $reference['path'];
        $name = basename($path);
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return [
            'name' => $name,
            'scope' => $scope,
            'eventUid' => $eventUid,
            'size' => 0,
            'modified' => null,
            'path' => $path,
            'url' => null,
            'extension' => $extension,
            'mime' => null,
            'tags' => [],
            'folder' => null,
            'missing' => true,
            'landing' => $reference + ['missing' => true],
        ];
    }

    /**
     * Handle file uploads.
     */
    public function upload(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $body = [];
        }
        $scope = (string) ($body['scope'] ?? MediaLibraryService::SCOPE_GLOBAL);
        $eventUid = (string) ($body['event'] ?? '');
        if ($eventUid === '') {
            $eventUid = (string) ($_SESSION['event_uid'] ?? $this->config->getActiveEventUid());
        }

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
            $stored = $this->media->uploadFile($scope, $file, $eventUid !== '' ? $eventUid : null, $options !== [] ? $options : null);
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
    public function replace(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $body = [];
        }

        $scope = (string) ($body['scope'] ?? MediaLibraryService::SCOPE_GLOBAL);
        $name = (string) ($body['name'] ?? '');
        $eventUid = (string) ($body['event'] ?? '');
        if ($eventUid === '') {
            $eventUid = (string) ($_SESSION['event_uid'] ?? $this->config->getActiveEventUid());
        }

        if ($name === '') {
            return $this->jsonError($response, 'invalid filename', 400);
        }

        $files = $request->getUploadedFiles();
        if (!isset($files['file'])) {
            return $this->jsonError($response, 'missing file', 400);
        }

        $file = $files['file'];

        try {
            $stored = $this->media->replaceFile($scope, $name, $file, $eventUid !== '' ? $eventUid : null);
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
     * Rename a file.
     */
    public function rename(Request $request, Response $response): Response
    {
        $data = $this->parseBody($request);
        $scope = (string) ($data['scope'] ?? MediaLibraryService::SCOPE_GLOBAL);
        $old = (string) ($data['oldName'] ?? '');
        $new = (string) ($data['newName'] ?? '');
        $eventUid = (string) ($data['event'] ?? '');
        if ($eventUid === '') {
            $eventUid = (string) ($_SESSION['event_uid'] ?? $this->config->getActiveEventUid());
        }

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
                $eventUid !== '' ? $eventUid : null,
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
    public function delete(Request $request, Response $response): Response
    {
        $data = $this->parseBody($request);
        $scope = (string) ($data['scope'] ?? MediaLibraryService::SCOPE_GLOBAL);
        $name = (string) ($data['name'] ?? '');
        $eventUid = (string) ($data['event'] ?? '');
        if ($eventUid === '') {
            $eventUid = (string) ($_SESSION['event_uid'] ?? $this->config->getActiveEventUid());
        }

        if ($name === '') {
            return $this->jsonError($response, 'invalid filename', 400);
        }

        try {
            $this->media->deleteFile($scope, $name, $eventUid !== '' ? $eventUid : null);
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
    private function collectTags(array $files): array
    {
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
    private function collectFolders(array $files): array
    {
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
    private function normalizeTags($value): array
    {
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
    private function normalizeFolder($value): ?string
    {
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
    private function extractMetadata(array $input): array
    {
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
    private function json(Response $response, array $payload, int $status = 200): Response
    {
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

    private function jsonError(Response $response, string $message, int $status): Response
    {
        $payload = [
            'error' => $message,
            'limits' => $this->media->getLimits(),
        ];

        return $this->json($response, $payload, $status);
    }

    /**
     * @return array<string,mixed>
     */
    private function parseBody(Request $request): array
    {
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

