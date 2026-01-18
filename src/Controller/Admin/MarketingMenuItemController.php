<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CmsMenuItem;
use App\Service\CmsMenuDefinitionService;
use App\Service\CmsPageMenuService;
use App\Service\NamespaceResolver;
use App\Support\BasePathHelper;
use App\Support\MarketingMenuItemValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Routing\RouteContext;

final class MarketingMenuItemController
{
    private CmsMenuDefinitionService $menuDefinitions;
    private CmsPageMenuService $menuService;
    private NamespaceResolver $namespaceResolver;
    private MarketingMenuItemValidator $validator;

    public function __construct(
        ?CmsMenuDefinitionService $menuDefinitions = null,
        ?CmsPageMenuService $menuService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?MarketingMenuItemValidator $validator = null
    ) {
        $this->menuDefinitions = $menuDefinitions ?? new CmsMenuDefinitionService();
        $this->menuService = $menuService ?? new CmsPageMenuService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->validator = $validator ?? new MarketingMenuItemValidator();
    }

    /**
     * List menu items for a menu definition.
     *
     * @param array{menuId:string} $args
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            return $this->jsonError($response, 'Menu id is required.', 422);
        }

        $menu = $this->menuDefinitions->getMenuById($namespace, $menuId);
        if ($menu === null) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        $params = $request->getQueryParams();
        $locale = null;
        if (array_key_exists('locale', $params) && is_string($params['locale'])) {
            $candidate = strtolower(trim($params['locale']));
            if ($candidate !== '') {
                $locale = $candidate;
            }
        }

        $onlyActive = $this->normalizeBoolean($params['onlyActive'] ?? false);
        if (array_key_exists('includeInactive', $params)) {
            $onlyActive = !$this->normalizeBoolean($params['includeInactive']);
        }

        $items = $this->menuDefinitions->getMenuItemsForMenu($namespace, $menuId, $locale, $onlyActive);

        return $this->json($response, [
            'menu' => [
                'id' => $menu->getId(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
            ],
            'items' => array_map(fn (CmsMenuItem $item): array => $this->serializeItem($item), $items),
        ]);
    }

    /**
     * Create a menu item for a menu definition.
     *
     * @param array{menuId:string} $args
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            return $this->jsonError($response, 'Menu id is required.', 422);
        }

        $menu = $this->menuDefinitions->getMenuById($namespace, $menuId);
        if ($menu === null) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        [$data, $errors] = $this->validator->validatePayload($payload, $basePath);
        if ($errors !== []) {
            return $this->jsonError($response, 'Validation failed.', 422, $errors);
        }

        try {
            $item = $this->menuService->createMenuItemForMenu(
                $menuId,
                $namespace,
                $data['label'],
                $data['href'],
                $data['icon'],
                $data['parentId'],
                $data['layout'],
                $data['detailTitle'],
                $data['detailText'],
                $data['detailSubline'],
                $data['position'],
                $data['isExternal'],
                $data['locale'],
                $data['isActive'],
                $data['isStartpage']
            );
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        return $this->json($response, [
            'item' => $this->serializeItem($item),
        ], 201);
    }

    /**
     * Update a menu item for a menu definition.
     *
     * @param array{menuId:string,id:string} $args
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        $itemId = isset($args['id']) ? (int) $args['id'] : 0;
        if ($menuId <= 0 || $itemId <= 0) {
            return $this->jsonError($response, 'Menu id and item id are required.', 422);
        }

        $menu = $this->menuDefinitions->getMenuById($namespace, $menuId);
        if ($menu === null) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        $item = $this->menuService->getMenuItemById($itemId);
        if ($item === null || $item->getMenuId() !== $menuId || $item->getNamespace() !== $namespace) {
            return $this->jsonError($response, 'Menu item not found.', 404);
        }

        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        [$data, $errors] = $this->validator->validatePayload($payload, $basePath);
        if ($errors !== []) {
            return $this->jsonError($response, 'Validation failed.', 422, $errors);
        }

        try {
            $item = $this->menuService->updateMenuItem(
                $itemId,
                $data['label'],
                $data['href'],
                $data['icon'],
                $data['parentId'],
                $data['layout'],
                $data['detailTitle'],
                $data['detailText'],
                $data['detailSubline'],
                $data['position'],
                $data['isExternal'],
                $data['locale'],
                $data['isActive'],
                $data['isStartpage']
            );
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        return $this->json($response, [
            'item' => $this->serializeItem($item),
        ]);
    }

    /**
     * Delete a menu item for a menu definition.
     *
     * @param array{menuId:string,id:string} $args
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        $itemId = isset($args['id']) ? (int) $args['id'] : 0;
        if ($menuId <= 0 || $itemId <= 0) {
            return $this->jsonError($response, 'Menu id and item id are required.', 422);
        }

        $menu = $this->menuDefinitions->getMenuById($namespace, $menuId);
        if ($menu === null) {
            return $this->jsonError($response, 'Menu not found.', 404);
        }

        $item = $this->menuService->getMenuItemById($itemId);
        if ($item === null || $item->getMenuId() !== $menuId || $item->getNamespace() !== $namespace) {
            return $this->jsonError($response, 'Menu item not found.', 404);
        }

        $this->menuService->deleteMenuItem($itemId);

        return $response->withStatus(204);
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
    private function serializeItem(CmsMenuItem $item): array
    {
        return [
            'id' => $item->getId(),
            'menuId' => $item->getMenuId(),
            'namespace' => $item->getNamespace(),
            'label' => $item->getLabel(),
            'href' => $item->getHref(),
            'icon' => $item->getIcon(),
            'parentId' => $item->getParentId(),
            'layout' => $item->getLayout(),
            'detailTitle' => $item->getDetailTitle(),
            'detailText' => $item->getDetailText(),
            'detailSubline' => $item->getDetailSubline(),
            'position' => $item->getPosition(),
            'isExternal' => $item->isExternal(),
            'locale' => $item->getLocale(),
            'isActive' => $item->isActive(),
            'isStartpage' => $item->isStartpage(),
            'updatedAt' => $item->getUpdatedAt()?->format(DATE_ATOM),
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
