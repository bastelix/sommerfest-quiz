<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CmsMenu;
use App\Service\CmsMenuDefinitionService;
use App\Service\NamespaceResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class MarketingMenuDefinitionController
{
    private const MAX_LABEL_LENGTH = 64;
    private const MAX_LOCALE_LENGTH = 8;

    private CmsMenuDefinitionService $menuDefinitions;
    private NamespaceResolver $namespaceResolver;

    public function __construct(
        ?CmsMenuDefinitionService $menuDefinitions = null,
        ?NamespaceResolver $namespaceResolver = null
    ) {
        $this->menuDefinitions = $menuDefinitions ?? new CmsMenuDefinitionService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    /**
     * List menu definitions for the resolved namespace.
     */
    public function index(Request $request, Response $response): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $params = $request->getQueryParams();
        $onlyActive = $this->normalizeBoolean($params['onlyActive'] ?? false);
        if (array_key_exists('includeInactive', $params)) {
            $onlyActive = !$this->normalizeBoolean($params['includeInactive']);
        }

        $menus = $this->menuDefinitions->listMenus($namespace, $onlyActive);

        return $this->json($response, [
            'menus' => array_map(fn (CmsMenu $menu): array => $this->serializeMenu($menu), $menus),
        ]);
    }

    /**
     * Fetch a single menu definition by id.
     *
     * @param array{id:string} $args
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $menuId = (int) $args['id'];
        if ($menuId <= 0) {
            return $this->jsonError($response, 'Menu id is required.', 422);
        }

        $menu = $this->menuDefinitions->getMenuById($namespace, $menuId);
        if ($menu === null) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        return $this->json($response, [
            'menu' => $this->serializeMenu($menu),
        ]);
    }

    /**
     * Create a new menu definition.
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

        try {
            $menu = $this->menuDefinitions->createMenu(
                $namespace,
                $data['label'],
                $data['locale'],
                $data['isActive']
            );
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        return $this->json($response, [
            'menu' => $this->serializeMenu($menu),
        ], 201);
    }

    /**
     * Update an existing menu definition.
     *
     * @param array{id:string} $args
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $menuId = (int) $args['id'];
        if ($menuId <= 0) {
            return $this->jsonError($response, 'Menu id is required.', 422);
        }

        $existing = $this->menuDefinitions->getMenuById($namespace, $menuId);
        if ($existing === null) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        [$data, $errors] = $this->validatePayload($payload, $existing);
        if ($errors !== []) {
            return $this->jsonError($response, 'Validation failed.', 422, $errors);
        }

        try {
            $menu = $this->menuDefinitions->updateMenu(
                $namespace,
                $menuId,
                $data['label'],
                $data['locale'],
                $data['isActive']
            );
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        return $this->json($response, [
            'menu' => $this->serializeMenu($menu),
        ]);
    }

    /**
     * Delete a menu definition.
     *
     * @param array{id:string} $args
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $menuId = (int) $args['id'];
        if ($menuId <= 0) {
            return $this->jsonError($response, 'Menu id is required.', 422);
        }

        if (!$this->menuDefinitions->deleteMenu($namespace, $menuId)) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        return $response->withStatus(204);
    }

    /**
     * @return array{array{label: string, locale: string, isActive: bool}, array<string, string>}
     */
    private function validatePayload(array $payload, ?CmsMenu $existing = null): array
    {
        $errors = [];

        $label = isset($payload['label']) ? trim((string) $payload['label']) : null;
        if ($label === null || $label === '') {
            if ($existing === null) {
                $errors['label'] = 'Label is required.';
            } else {
                $label = $existing->getLabel();
            }
        } elseif (mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            $errors['label'] = sprintf('Label must be at most %d characters.', self::MAX_LABEL_LENGTH);
        }

        $locale = isset($payload['locale']) ? strtolower(trim((string) $payload['locale'])) : null;
        if ($locale === null || $locale === '') {
            $locale = $existing?->getLocale() ?? 'de';
        } elseif (mb_strlen($locale) > self::MAX_LOCALE_LENGTH) {
            $errors['locale'] = sprintf('Locale must be at most %d characters.', self::MAX_LOCALE_LENGTH);
        }

        $isActive = $existing?->isActive() ?? true;
        if (array_key_exists('isActive', $payload)) {
            $isActive = $this->normalizeBoolean($payload['isActive']);
        }

        $data = [
            'label' => $label ?? '',
            'locale' => $locale,
            'isActive' => $isActive,
        ];

        return [$data, $errors];
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
    private function serializeMenu(CmsMenu $menu): array
    {
        return [
            'id' => $menu->getId(),
            'namespace' => $menu->getNamespace(),
            'label' => $menu->getLabel(),
            'locale' => $menu->getLocale(),
            'isActive' => $menu->isActive(),
            'updatedAt' => $menu->getUpdatedAt()?->format(DATE_ATOM),
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
