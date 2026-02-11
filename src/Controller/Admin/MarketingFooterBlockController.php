<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CmsFooterBlock;
use App\Repository\ProjectSettingsRepository;
use App\Service\CmsFooterBlockService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class MarketingFooterBlockController
{
    private CmsFooterBlockService $blockService;

    public function __construct(?CmsFooterBlockService $blockService = null)
    {
        $this->blockService = $blockService ?? new CmsFooterBlockService();
    }

    /**
     * GET /admin/footer-blocks
     * List all footer blocks for a namespace/slot/locale
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $namespace = (string) ($params['namespace'] ?? 'default');
        $slot = (string) ($params['slot'] ?? 'footer_1');
        $locale = isset($params['locale']) && is_string($params['locale']) ? $params['locale'] : null;

        $blocks = $this->blockService->getBlocksForSlot($namespace, $slot, $locale, false);

        $payload = [
            'blocks' => array_map(fn(CmsFooterBlock $block) => $this->serializeBlock($block), $blocks),
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /admin/footer-blocks
     * Create a new footer block
     */
    public function create(Request $request, Response $response): Response
    {
        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $validation = $this->validateBlockPayload($payload);
        if ($validation['errors'] !== []) {
            return $this->jsonError($response, 'Validation failed.', 422, $validation['errors']);
        }

        $data = $validation['data'];

        try {
            $block = $this->blockService->createBlock(
                $data['namespace'],
                $data['slot'],
                $data['type'],
                $data['content'],
                $data['position'],
                $data['locale'],
                $data['isActive']
            );

            $payload = ['block' => $this->serializeBlock($block)];
            $response->getBody()->write(json_encode($payload));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 422);
        }
    }

    /**
     * PUT /admin/footer-blocks/{id}
     * Update an existing footer block
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonError($response, 'Invalid block ID.', 400);
        }

        $existing = $this->blockService->getBlockById($id);
        if ($existing === null) {
            return $this->jsonError($response, 'Footer block not found.', 404);
        }

        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $validation = $this->validateBlockPayload($payload, true);
        if ($validation['errors'] !== []) {
            return $this->jsonError($response, 'Validation failed.', 422, $validation['errors']);
        }

        $data = $validation['data'];

        try {
            $slot = isset($payload['slot']) && is_string($payload['slot']) ? $payload['slot'] : null;

            $block = $this->blockService->updateBlock(
                $id,
                $data['type'],
                $data['content'],
                $data['position'],
                $data['isActive'],
                $slot
            );

            $payload = ['block' => $this->serializeBlock($block)];
            $response->getBody()->write(json_encode($payload));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 422);
        }
    }

    /**
     * DELETE /admin/footer-blocks/{id}
     * Delete a footer block
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonError($response, 'Invalid block ID.', 400);
        }

        try {
            $this->blockService->deleteBlock($id);
            return $response->withStatus(204);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 404);
        }
    }

    /**
     * PUT /admin/footer-blocks/layout
     * Save the footer layout preference
     */
    public function saveLayout(Request $request, Response $response): Response
    {
        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $namespace = (string) ($payload['namespace'] ?? '');
        $layout = (string) ($payload['layout'] ?? '');

        if ($namespace === '') {
            return $this->jsonError($response, 'Namespace is required.', 422);
        }

        $allowedLayouts = ['equal', 'brand-left', 'cta-right', 'centered'];
        if (!in_array($layout, $allowedLayouts, true)) {
            return $this->jsonError($response, 'Invalid layout. Allowed: ' . implode(', ', $allowedLayouts), 422);
        }

        $repo = new ProjectSettingsRepository();
        $repo->updateFooterLayout($namespace, $layout);

        $response->getBody()->write(json_encode(['layout' => $layout]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /admin/footer-blocks/reorder
     * Reorder blocks within a slot
     */
    public function reorder(Request $request, Response $response): Response
    {
        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $namespace = (string) ($payload['namespace'] ?? 'default');
        $slot = (string) ($payload['slot'] ?? '');
        $locale = (string) ($payload['locale'] ?? 'de');
        $orderedIds = $payload['orderedIds'] ?? [];

        if (!is_array($orderedIds)) {
            return $this->jsonError($response, 'orderedIds must be an array.', 422);
        }

        try {
            $this->blockService->reorderBlocks($namespace, $slot, $locale, $orderedIds);
            return $response->withStatus(204);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, $e->getMessage(), 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBlock(CmsFooterBlock $block): array
    {
        return [
            'id' => $block->getId(),
            'namespace' => $block->getNamespace(),
            'slot' => $block->getSlot(),
            'type' => $block->getType(),
            'content' => $block->getContent(),
            'position' => $block->getPosition(),
            'locale' => $block->getLocale(),
            'isActive' => $block->isActive(),
            'updatedAt' => $block->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    private function validateBlockPayload(array $payload, bool $isUpdate = false): array
    {
        $errors = [];
        $data = [];

        // Required for create only
        if (!$isUpdate) {
            if (!isset($payload['namespace']) || !is_string($payload['namespace']) || trim($payload['namespace']) === '') {
                $errors['namespace'] = 'Namespace is required.';
            } else {
                $data['namespace'] = trim($payload['namespace']);
            }

            if (!isset($payload['slot']) || !is_string($payload['slot'])) {
                $errors['slot'] = 'Slot is required.';
            } else {
                $data['slot'] = $payload['slot'];
            }

            $data['locale'] = isset($payload['locale']) && is_string($payload['locale'])
                ? trim($payload['locale'])
                : 'de';
        }

        // Required for both create and update
        if (!isset($payload['type']) || !is_string($payload['type'])) {
            $errors['type'] = 'Type is required.';
        } else {
            $data['type'] = $payload['type'];
        }

        if (!isset($payload['content']) || !is_array($payload['content'])) {
            $errors['content'] = 'Content must be an object.';
        } else {
            $data['content'] = $payload['content'];
        }

        $data['position'] = isset($payload['position']) && is_int($payload['position'])
            ? $payload['position']
            : 0;

        $data['isActive'] = isset($payload['isActive']) && is_bool($payload['isActive'])
            ? $payload['isActive']
            : true;

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * @return array<string, mixed> | null
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
     * @param array<string, string> $fields
     */
    private function jsonError(Response $response, string $message, int $status, array $fields = []): Response
    {
        $payload = ['error' => $message];
        if ($fields !== []) {
            $payload['fields'] = $fields;
        }

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
