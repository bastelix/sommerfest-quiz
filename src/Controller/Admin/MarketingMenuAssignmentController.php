<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CmsMenuAssignment;
use App\Service\CmsMenuDefinitionService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class MarketingMenuAssignmentController
{
    private const MAX_LOCALE_LENGTH = 8;

    /** @var string[] */
    private const ALLOWED_SLOTS = ['main', 'footer_1', 'footer_2', 'footer_3'];

    private CmsMenuDefinitionService $menuDefinitions;
    private PageService $pageService;
    private NamespaceResolver $namespaceResolver;

    public function __construct(
        ?CmsMenuDefinitionService $menuDefinitions = null,
        ?PageService $pageService = null,
        ?NamespaceResolver $namespaceResolver = null
    ) {
        $this->menuDefinitions = $menuDefinitions ?? new CmsMenuDefinitionService();
        $this->pageService = $pageService ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    /**
     * List menu assignments for the resolved namespace.
     */
    public function index(Request $request, Response $response): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $params = $request->getQueryParams();

        $menuId = $this->parseOptionalInt($params['menuId'] ?? null);
        $pageId = $this->parseOptionalInt($params['pageId'] ?? null);
        $slot = isset($params['slot']) ? strtolower(trim((string) $params['slot'])) : null;
        $locale = isset($params['locale']) ? strtolower(trim((string) $params['locale'])) : null;

        if ($slot !== null && $slot !== '' && !in_array($slot, self::ALLOWED_SLOTS, true)) {
            return $this->jsonError($response, 'Slot is invalid.', 422, ['slot' => 'Slot is invalid.']);
        }

        if ($locale !== null && $locale !== '' && mb_strlen($locale) > self::MAX_LOCALE_LENGTH) {
            return $this->jsonError($response, 'Locale is invalid.', 422, [
                'locale' => sprintf('Locale must be at most %d characters.', self::MAX_LOCALE_LENGTH),
            ]);
        }

        $onlyActive = $this->normalizeBoolean($params['onlyActive'] ?? false);
        if (array_key_exists('includeInactive', $params)) {
            $onlyActive = !$this->normalizeBoolean($params['includeInactive']);
        }

        $assignments = $this->menuDefinitions->listAssignments(
            $namespace,
            $menuId,
            $pageId,
            $slot !== '' ? $slot : null,
            $locale !== '' ? $locale : null,
            $onlyActive
        );

        return $this->json($response, [
            'assignments' => array_map(
                fn (CmsMenuAssignment $assignment): array => $this->serializeAssignment($assignment),
                $assignments
            ),
        ]);
    }

    /**
     * Fetch a single menu assignment by id.
     *
     * @param array{id:string} $args
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $assignmentId = isset($args['id']) ? (int) $args['id'] : 0;
        if ($assignmentId <= 0) {
            return $this->jsonError($response, 'Assignment id is required.', 422);
        }

        $assignment = $this->menuDefinitions->getAssignmentById($namespace, $assignmentId);
        if ($assignment === null) {
            return $this->jsonError($response, 'Menu assignment not found.', 404);
        }

        return $this->json($response, [
            'assignment' => $this->serializeAssignment($assignment),
        ]);
    }

    /**
     * Create a menu assignment.
     */
    public function create(Request $request, Response $response): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        [$data, $errors] = $this->validatePayload($payload);
        if ($errors !== []) {
            return $this->jsonError($response, 'Validation failed.', 422, $errors);
        }

        $menu = $this->menuDefinitions->getMenuById($namespace, $data['menuId']);
        if ($menu === null) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        $page = $this->pageService->findById($data['pageId']);
        if ($page === null || $page->getNamespace() !== $namespace) {
            return $this->jsonError($response, 'Page not found.', 404);
        }

        try {
            $assignment = $this->menuDefinitions->createAssignment(
                $namespace,
                $data['menuId'],
                $data['pageId'],
                $data['slot'],
                $data['locale'],
                $data['isActive']
            );
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        return $this->json($response, [
            'assignment' => $this->serializeAssignment($assignment),
        ], 201);
    }

    /**
     * Update a menu assignment.
     *
     * @param array{id:string} $args
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $assignmentId = isset($args['id']) ? (int) $args['id'] : 0;
        if ($assignmentId <= 0) {
            return $this->jsonError($response, 'Assignment id is required.', 422);
        }

        $existing = $this->menuDefinitions->getAssignmentById($namespace, $assignmentId);
        if ($existing === null) {
            return $this->jsonError($response, 'Menu assignment not found.', 404);
        }

        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        [$data, $errors] = $this->validatePayload($payload, $existing);
        if ($errors !== []) {
            return $this->jsonError($response, 'Validation failed.', 422, $errors);
        }

        $menu = $this->menuDefinitions->getMenuById($namespace, $data['menuId']);
        if ($menu === null) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        $page = $this->pageService->findById($data['pageId']);
        if ($page === null || $page->getNamespace() !== $namespace) {
            return $this->jsonError($response, 'Page not found.', 404);
        }

        try {
            $assignment = $this->menuDefinitions->updateAssignment(
                $namespace,
                $assignmentId,
                $data['menuId'],
                $data['pageId'],
                $data['slot'],
                $data['locale'],
                $data['isActive']
            );
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        return $this->json($response, [
            'assignment' => $this->serializeAssignment($assignment),
        ]);
    }

    /**
     * Delete a menu assignment.
     *
     * @param array{id:string} $args
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $assignmentId = isset($args['id']) ? (int) $args['id'] : 0;
        if ($assignmentId <= 0) {
            return $this->jsonError($response, 'Assignment id is required.', 422);
        }

        if (!$this->menuDefinitions->deleteAssignment($namespace, $assignmentId)) {
            return $this->jsonError($response, 'Menu assignment not found.', 404);
        }

        return $response->withStatus(204);
    }

    /**
     * @return array{
     *     array{menuId: int, pageId: int, slot: string, locale: string, isActive: bool},
     *     array<string, string>
     * }
     */
    private function validatePayload(array $payload, ?CmsMenuAssignment $existing = null): array
    {
        $errors = [];

        $menuId = isset($payload['menuId']) ? (int) $payload['menuId'] : 0;
        if ($menuId <= 0) {
            $errors['menuId'] = 'Menu id is required.';
        }

        $pageId = isset($payload['pageId']) ? (int) $payload['pageId'] : 0;
        if ($pageId <= 0) {
            $errors['pageId'] = 'Page id is required.';
        }

        $slot = isset($payload['slot']) ? strtolower(trim((string) $payload['slot'])) : '';
        if ($slot === '') {
            $errors['slot'] = 'Slot is required.';
        } elseif (!in_array($slot, self::ALLOWED_SLOTS, true)) {
            $errors['slot'] = 'Slot is invalid.';
        }

        $locale = isset($payload['locale']) ? strtolower(trim((string) $payload['locale'])) : null;
        if ($locale !== null && $locale !== '' && mb_strlen($locale) > self::MAX_LOCALE_LENGTH) {
            $errors['locale'] = sprintf('Locale must be at most %d characters.', self::MAX_LOCALE_LENGTH);
        }

        $isActive = $existing?->isActive() ?? false;
        if (!array_key_exists('isActive', $payload)) {
            $errors['isActive'] = 'isActive is required.';
        } else {
            $isActive = $this->normalizeBoolean($payload['isActive']);
        }

        if ($existing !== null) {
            if ($menuId <= 0) {
                $menuId = $existing->getMenuId();
            }
            if ($pageId <= 0) {
                $pageId = $existing->getPageId() ?? 0;
            }
            if ($slot === '') {
                $slot = $existing->getSlot();
            }
            if ($locale === null || $locale === '') {
                $locale = $existing->getLocale();
            }
        }

        $data = [
            'menuId' => $menuId,
            'pageId' => $pageId,
            'slot' => $slot,
            'locale' => $locale ?? 'de',
            'isActive' => $isActive,
        ];

        return [$data, $errors];
    }

    private function parseOptionalInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $parsed = (int) $value;
        return $parsed > 0 ? $parsed : null;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAssignment(CmsMenuAssignment $assignment): array
    {
        return [
            'id' => $assignment->getId(),
            'menuId' => $assignment->getMenuId(),
            'pageId' => $assignment->getPageId(),
            'namespace' => $assignment->getNamespace(),
            'slot' => $assignment->getSlot(),
            'locale' => $assignment->getLocale(),
            'isActive' => $assignment->isActive(),
            'updatedAt' => $assignment->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed> | null
     */
    private function parseJsonBody(Request $request): ?array
    {
        $body = $request->getParsedBody();
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) $request->getBody();
            if ($raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }

            return $decoded;
        }

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

    /**
     * @param array<string, mixed> $payload
     */
    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
