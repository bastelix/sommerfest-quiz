<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Service\CmsMenuDefinitionService;
use App\Service\CmsPageMenuService;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NamespaceMenuController
{
    public const SCOPE_MENU_READ = 'menu:read';
    public const SCOPE_MENU_WRITE = 'menu:write';

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?CmsMenuDefinitionService $menus = null,
        private readonly ?CmsPageMenuService $menuItems = null,
    ) {
    }

    public function listMenus(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->menus ?? new CmsMenuDefinitionService($pdo);

        $out = [];
        foreach ($svc->listMenus($ns, false) as $menu) {
            $out[] = [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }

        return $this->json($response, ['namespace' => $ns, 'menus' => $out]);
    }

    public function createMenu(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = isset($payload['label']) && is_string($payload['label']) ? trim($payload['label']) : '';
        if ($label === '') {
            return $this->json($response, ['error' => 'missing_label'], 422);
        }

        $locale = isset($payload['locale']) && is_string($payload['locale']) ? trim($payload['locale']) : null;
        $isActive = array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true;

        $pdo = $this->resolvePdo($request);
        $svc = $this->menus ?? new CmsMenuDefinitionService($pdo);

        try {
            $menu = $svc->createMenu($ns, $label, $locale, $isActive);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'create_failed', 'message' => $e->getMessage()], 500);
        }

        return $this->json($response, [
            'status' => 'created',
            'menu' => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(DATE_ATOM),
            ],
        ], 201);
    }

    public function updateMenu(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $menuId = (int) ($args['menuId'] ?? 0);
        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = isset($payload['label']) && is_string($payload['label']) ? trim($payload['label']) : '';
        if ($label === '') {
            return $this->json($response, ['error' => 'missing_label'], 422);
        }

        $locale = isset($payload['locale']) && is_string($payload['locale']) ? trim($payload['locale']) : null;
        $isActive = array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true;

        $pdo = $this->resolvePdo($request);
        $svc = $this->menus ?? new CmsMenuDefinitionService($pdo);

        try {
            $menu = $svc->updateMenu($ns, $menuId, $label, $locale, $isActive);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'update_failed', 'message' => $e->getMessage()], 500);
        }

        return $this->json($response, [
            'status' => 'updated',
            'menu' => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(DATE_ATOM),
            ],
        ]);
    }

    public function deleteMenu(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $menuId = (int) ($args['menuId'] ?? 0);
        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->menus ?? new CmsMenuDefinitionService($pdo);
        $svc->deleteMenu($ns, $menuId);

        return $this->json($response, ['status' => 'deleted']);
    }

    public function listMenuItems(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $menuId = (int) ($args['menuId'] ?? 0);
        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $params = $request->getQueryParams();
        $locale = isset($params['locale']) && is_string($params['locale']) ? (string) $params['locale'] : null;

        $pdo = $this->resolvePdo($request);
        $svc = $this->menus ?? new CmsMenuDefinitionService($pdo);
        $items = $svc->getMenuItemsForMenu($ns, $menuId, $locale, false);

        $nodes = [];
        foreach ($items as $item) {
            $nodes[$item->getId()] = [
                'id' => $item->getId(),
                'menuId' => $item->getMenuId(),
                'namespace' => $item->getNamespace(),
                'parentId' => $item->getParentId(),
                'label' => $item->getLabel(),
                'href' => $item->getHref(),
                'icon' => $item->getIcon(),
                'layout' => $item->getLayout(),
                'detailTitle' => $item->getDetailTitle(),
                'detailText' => $item->getDetailText(),
                'detailSubline' => $item->getDetailSubline(),
                'position' => $item->getPosition(),
                'locale' => $item->getLocale(),
                'isExternal' => $item->isExternal(),
                'isActive' => $item->isActive(),
                'isStartpage' => $item->isStartpage(),
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $node['parentId'];
            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $this->json($response, [
            'namespace' => $ns,
            'menuId' => $menuId,
            'locale' => $locale,
            'items' => array_values($tree),
        ]);
    }

    public function createMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $menuId = (int) ($args['menuId'] ?? 0);
        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = isset($payload['label']) && is_string($payload['label']) ? (string) $payload['label'] : '';
        $href = isset($payload['href']) && is_string($payload['href']) ? (string) $payload['href'] : '';
        if (trim($label) === '' || trim($href) === '') {
            return $this->json($response, ['error' => 'missing_label_or_href'], 422);
        }

        $pdo = $this->resolvePdo($request);
        $itemsSvc = $this->menuItems ?? new CmsPageMenuService($pdo);

        try {
            $item = $itemsSvc->createMenuItemForMenu(
                $menuId,
                $ns,
                $label,
                $href,
                isset($payload['icon']) && is_string($payload['icon'])
                    ? $payload['icon'] : null,
                isset($payload['parentId'])
                    ? (is_numeric($payload['parentId']) ? (int) $payload['parentId'] : null)
                    : null,
                isset($payload['layout']) && is_string($payload['layout'])
                    ? $payload['layout'] : 'link',
                isset($payload['detailTitle']) && is_string($payload['detailTitle'])
                    ? $payload['detailTitle'] : null,
                isset($payload['detailText']) && is_string($payload['detailText'])
                    ? $payload['detailText'] : null,
                isset($payload['detailSubline']) && is_string($payload['detailSubline'])
                    ? $payload['detailSubline'] : null,
                isset($payload['position']) && is_numeric($payload['position']) ? (int) $payload['position'] : null,
                isset($payload['isExternal']) ? (bool) $payload['isExternal'] : false,
                isset($payload['locale']) && is_string($payload['locale']) ? $payload['locale'] : null,
                array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true,
                isset($payload['isStartpage']) ? (bool) $payload['isStartpage'] : false,
            );
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'create_item_failed', 'message' => $e->getMessage()], 500);
        }

        return $this->json($response, ['status' => 'created', 'id' => $item->getId()], 201);
    }

    public function updateMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $itemId = (int) ($args['itemId'] ?? 0);
        if ($itemId <= 0) {
            return $this->json($response, ['error' => 'invalid_item_id'], 400);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = isset($payload['label']) && is_string($payload['label']) ? (string) $payload['label'] : '';
        $href = isset($payload['href']) && is_string($payload['href']) ? (string) $payload['href'] : '';
        if (trim($label) === '' || trim($href) === '') {
            return $this->json($response, ['error' => 'missing_label_or_href'], 422);
        }

        $pdo = $this->resolvePdo($request);
        $itemsSvc = $this->menuItems ?? new CmsPageMenuService($pdo);

        try {
            $item = $itemsSvc->updateMenuItem(
                $itemId,
                $label,
                $href,
                isset($payload['icon']) && is_string($payload['icon'])
                    ? $payload['icon'] : null,
                isset($payload['parentId'])
                    ? (is_numeric($payload['parentId']) ? (int) $payload['parentId'] : null)
                    : null,
                isset($payload['layout']) && is_string($payload['layout'])
                    ? $payload['layout'] : 'link',
                isset($payload['detailTitle']) && is_string($payload['detailTitle'])
                    ? $payload['detailTitle'] : null,
                isset($payload['detailText']) && is_string($payload['detailText'])
                    ? $payload['detailText'] : null,
                isset($payload['detailSubline']) && is_string($payload['detailSubline'])
                    ? $payload['detailSubline'] : null,
                isset($payload['position']) && is_numeric($payload['position']) ? (int) $payload['position'] : null,
                isset($payload['isExternal']) ? (bool) $payload['isExternal'] : false,
                isset($payload['locale']) && is_string($payload['locale']) ? $payload['locale'] : null,
                array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true,
                isset($payload['isStartpage']) ? (bool) $payload['isStartpage'] : false,
            );
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'update_item_failed', 'message' => $e->getMessage()], 500);
        }

        return $this->json($response, ['status' => 'updated', 'id' => $item->getId()]);
    }

    public function deleteMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $itemId = (int) ($args['itemId'] ?? 0);
        if ($itemId <= 0) {
            return $this->json($response, ['error' => 'invalid_item_id'], 400);
        }

        $pdo = $this->resolvePdo($request);
        $itemsSvc = $this->menuItems ?? new CmsPageMenuService($pdo);
        $itemsSvc->deleteMenuItem($itemId);

        return $this->json($response, ['status' => 'deleted']);
    }

    private function resolvePdo(Request $request): PDO
    {
        $pdo = $this->pdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        return RequestDatabase::resolve($request);
    }

    private function requireNamespaceMatch(Request $request, array $args): ?string
    {
        $ns = isset($args['ns']) ? (string) $args['ns'] : '';
        $tokenNs = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);
        if ($ns === '' || $tokenNs === '' || $ns !== $tokenNs) {
            return null;
        }
        return $ns;
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
