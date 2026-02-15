<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\PageModule;
use App\Service\PageModuleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use InvalidArgumentException;
use RuntimeException;

/**
 * Admin JSON API for managing page modules (e.g. latest-news).
 */
final class PageModuleController
{
    private PageModuleService $modules;

    public function __construct(?PageModuleService $modules = null)
    {
        $this->modules = $modules ?? new PageModuleService();
    }

    /**
     * GET /admin/page-modules?page_id=…
     * List all modules for a given page.
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $pageId = isset($params['page_id']) ? (int) $params['page_id'] : 0;

        if ($pageId <= 0) {
            return $this->jsonError($response, 'page_id query parameter is required.', 400);
        }

        $modules = $this->modules->getModulesForPage($pageId);

        $payload = [
            'modules' => array_map([$this, 'serializeModule'], $modules),
            'allowedTypes' => PageModuleService::ALLOWED_TYPES,
            'allowedPositions' => PageModuleService::ALLOWED_POSITIONS,
        ];

        return $this->jsonResponse($response, $payload);
    }

    /**
     * POST /admin/page-modules
     * Create a new page module.
     */
    public function create(Request $request, Response $response): Response
    {
        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $pageId = isset($payload['page_id']) ? (int) $payload['page_id'] : 0;
        $type = isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : '';
        $config = isset($payload['config']) && is_array($payload['config']) ? $payload['config'] : [];
        $position = isset($payload['position']) && is_string($payload['position']) ? $payload['position'] : '';

        try {
            $module = $this->modules->create($pageId, $type, $config, $position);

            return $this->jsonResponse($response, ['module' => $this->serializeModule($module)], 201);
        } catch (InvalidArgumentException $e) {
            return $this->jsonError($response, $e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    /**
     * PUT /admin/page-modules/{id}
     * Update an existing page module.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonError($response, 'Invalid module ID.', 400);
        }

        $existing = $this->modules->findById($id);
        if ($existing === null) {
            return $this->jsonError($response, 'Page module not found.', 404);
        }

        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $type = isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : $existing->getType();
        $config = isset($payload['config']) && is_array($payload['config']) ? $payload['config'] : $existing->getConfig();
        $position = isset($payload['position']) && is_string($payload['position']) ? $payload['position'] : $existing->getPosition();

        try {
            $module = $this->modules->update($id, $type, $config, $position);

            return $this->jsonResponse($response, ['module' => $this->serializeModule($module)]);
        } catch (InvalidArgumentException $e) {
            return $this->jsonError($response, $e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /admin/page-modules/{id}
     * Delete a page module.
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonError($response, 'Invalid module ID.', 400);
        }

        $existing = $this->modules->findById($id);
        if ($existing === null) {
            return $this->jsonError($response, 'Page module not found.', 404);
        }

        $this->modules->delete($id);

        return $response->withStatus(204);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeModule(PageModule $module): array
    {
        return [
            'id' => $module->getId(),
            'page_id' => $module->getPageId(),
            'type' => $module->getType(),
            'config' => $module->getConfig(),
            'position' => $module->getPosition(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonBody(Request $request): ?array
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) $request->getBody();
            if ($raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        $body = $request->getParsedBody();
        return is_array($body) ? $body : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write((string) json_encode(['error' => $message]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
