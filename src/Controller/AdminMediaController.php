<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\MediaLibraryService;
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
    private ConfigService $config;

    public function __construct(MediaLibraryService $media, ConfigService $config)
    {
        $this->media = $media;
        $this->config = $config;
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

        $eventUid = (string) ($params['event'] ?? '');
        if ($eventUid === '') {
            $eventUid = (string) ($_SESSION['event_uid'] ?? $this->config->getActiveEventUid());
        }

        try {
            $files = $this->media->listFiles($scope, $eventUid !== '' ? $eventUid : null);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 400);
        }

        if ($search !== '') {
            $files = array_values(array_filter(
                $files,
                static fn(array $file): bool => stripos((string) $file['name'], $search) !== false
            ));
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
        ];

        return $this->json($response, $payload);
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

        try {
            $stored = $this->media->uploadFile($scope, $file, $eventUid !== '' ? $eventUid : null, [
                'name' => (string) ($body['name'] ?? ''),
            ]);
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

        try {
            $file = $this->media->renameFile($scope, $old, $new, $eventUid !== '' ? $eventUid : null);
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

