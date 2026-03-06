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
        $ns = (string) ($args['ns'] ?? '');
        if (!$this->nsOk($request, $ns)) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $svc = $this->menus ?? new CmsMenuDefinitionService($this->pdoFromRequest($request));
        $items = [];
        foreach ($svc->listMenus($ns, false) as $menu) {
            $items[] = [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }

        return $this->json($response, ['namespace' => $ns, 'menus' => $items]);
    }

    public function createMenu(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        if (!$this->nsOk($request, $ns)) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = isset($data['label']) && is_string($data['label']) ? trim($data['label']) : '';
        $locale = isset($data['locale']) && is_string($data['locale']) ? trim($data['locale']) : null;
        $isActive = array_key_exists('isActive', $data) ? (bool) $data['isActive'] : true;

        if ($label === '') {
            return $this->json($response, ['error' => 'missing_label'], 422);
        }

        $svc = $this->menus ?? new CmsMenuDefinitionService($this->pdoFromRequest($request));
        $menu = $svc->createMenu($ns, $label, $locale, $isActive);

        return $this->json($response, [
            'status' => 'created',
            'menu' => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
            ],
        ], 201);
    }

    public function updateMenu(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        if (!$this->nsOk($request, $ns)) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }
        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = isset($data['label']) && is_string($data['label']) ? trim($data['label']) : '';
        $locale = isset($data['locale']) && is_string($data['locale']) ? trim($data['locale']) : null;
        $isActive = array_key_exists('isActive', $data) ? (bool) $data['isActive'] : true;

        if ($label === '') {
            return $this->json($response, ['error' => 'missing_label'], 422);
        }

        $svc = $this->menus ?? new CmsMenuDefinitionService($this->pdoFromRequest($request));
        $menu = $svc->updateMenu($ns, $menuId, $label, $locale, $isActive);

        return $this->json($response, [
            'status' => 'updated',
            'menu' => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
            ],
        ]);
    }

    public function deleteMenu(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        if (!$this->nsOk($request, $ns)) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }
        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $svc = $this->menus ?? new CmsMenuDefinitionService($this->pdoFromRequest($request));
        $svc->deleteMenu($ns, $menuId);

        return $this->json($response, ['status' => 'deleted']);
    }

    public function listMenuItems(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        if (!$this->nsOk($request, $ns)) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }
        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $params = $request->getQueryParams();
        $locale = isset($params['locale']) && is_string($params['locale']) ? (string) $params['locale'] : null;

        $svc = $this->menus ?? new CmsMenuDefinitionService($this->pdoFromRequest($request));
        $items = [];
        foreach ($svc->getMenuItemsForMenu($ns, $menuId, $locale, false) as $item) {
            $items[] = [
                'id' => $item->getId(),
                'menuId' => $item->getMenuId(),
                'namespace' => $item->getNamespace(),
                'parentId' => $item->getParentId(),
                'label' => $item->getLabel(),
                'href' => $item->getHref(),
                'icon' => $item->getIcon(),
                'layout' => $item->getLayout(),
                'position' => $item->getPosition(),
                'isExternal' => $item->isExternal(),
                'locale' => $item->getLocale(),
                'isActive' => $item->isActive(),
                'isStartpage' => $item->isStartpage(),
            ];
        }

        return $this->json($response, ['namespace' => $ns, 'menuId' => $menuId, 'items' => $items]);
    }

    public function createMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        if (!$this->nsOk($request, $ns)) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }
        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = isset($data['label']) && is_string($data['label']) ? (string) $data['label'] : '';
        $href = isset($data['href']) && is_string($data['href']) ? (string) $data['href'] : '';
        if (trim($label) === '' || trim($href) === '') {
            return $this->json($response, ['error' => 'missing_label_or_href'], 422);
        }

        $pdo = $this->pdoFromRequest($request);
        $itemsSvc = $this->menuItems ?? new CmsPageMenuService($pdo);
        $item = $itemsSvc->createMenuItemForMenu(
            $menuId,
            $ns,
            $label,
            $href,
            isset($data['icon']) && is_string($data['icon']) ? $data['icon'] : null,
            isset($data['parentId']) ? (int) $data['parentId'] : null,
            isset($data['layout']) && is_string($data['layout']) ? $data['layout'] : CmsPageMenuService::DEFAULT_LAYOUT,
            isset($data['detailTitle']) && is_string($data['detailTitle']) ? $data['detailTitle'] : null,
            isset($data['detailText']) && is_string($data['detailText']) ? $data['detailText'] : null,
            isset($data['detailSubline']) && is_string($data['detailSubline']) ? $data['detailSubline'] : null,
            isset($data['position']) ? (int) $data['position'] : null,
            isset($data['isExternal']) ? (bool) $data['isExternal'] : false,
            isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null,
            array_key_exists('isActive', $data) ? (bool) $data['isActive'] : true,
            isset($data['isStartpage']) ? (bool) $data['isStartpage'] : false
        );

        return $this->json($response, ['status' => 'created', 'item' => $item], 201);
    }

    public function updateMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        $itemId = (int) ($args['itemId'] ?? 0);
        if (!$this->nsOk($request, $ns)) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }
        if ($menuId <= 0 || $itemId <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $pdo = $this->pdoFromRequest($request);
        $itemsSvc = $this->menuItems ?? new CmsPageMenuService($pdo);
        $existing = $itemsSvc->getMenuItemById($itemId);
        if ($existing === null || $existing->getNamespace() !== $ns || $existing->getMenuId() !== $menuId) {
            return $this->json($response, ['error' => 'item_not_found'], 404);
        }

        $label = isset($data['label']) && is_string($data['label']) ? (string) $data['label'] : $existing->getLabel();
        $href = isset($data['href']) && is_string($data['href']) ? (string) $data['href'] : $existing->getHref();

        $item = $itemsSvc->updateMenuItem(
            $itemId,
            $label,
            $href,
            isset($data['icon']) && is_string($data['icon']) ? $data['icon'] : $existing->getIcon(),
            array_key_exists('parentId', $data) ? (is_null($data['parentId']) ? null : (int) $data['parentId']) : $existing->getParentId(),
            isset($data['layout']) && is_string($data['layout']) ? $data['layout'] : $existing->getLayout(),
            array_key_exists('detailTitle', $data) ? (is_string($data['detailTitle']) ? $data['detailTitle'] : null) : $existing->getDetailTitle(),
            array_key_exists('detailText', $data) ? (is_string($data['detailText']) ? $data['detailText'] : null) : $existing->getDetailText(),
            array_key_exists('detailSubline', $data) ? (is_string($data['detailSubline']) ? $data['detailSubline'] : null) : $existing->getDetailSubline(),
            array_key_exists('position', $data) ? (is_null($data['position']) ? null : (int) $data['position']) : $existing->getPosition(),
            array_key_exists('isExternal', $data) ? (bool) $data['isExternal'] : $existing->isExternal(),
            array_key_exists('locale', $data) ? (is_string($data['locale']) ? $data['locale'] : null) : $existing->getLocale(),
            array_key_exists('isActive', $data) ? (bool) $data['isActive'] : $existing->isActive(),
            array_key_exists('isStartpage', $data) ? (bool) $data['isStartpage'] : $existing->isStartpage()
        );

        return $this->json($response, ['status' => 'updated', 'item' => $item]);
    }

    public function deleteMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        $itemId = (int) ($args['itemId'] ?? 0);
        if (!$this->nsOk($request, $ns)) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }
        if ($menuId <= 0 || $itemId <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $pdo = $this->pdoFromRequest($request);
        $itemsSvc = $this->menuItems ?? new CmsPageMenuService($pdo);
        $existing = $itemsSvc->getMenuItemById($itemId);
        if ($existing === null || $existing->getNamespace() !== $ns || $existing->getMenuId() !== $menuId) {
            return $this->json($response, ['error' => 'item_not_found'], 404);
        }

        $itemsSvc->deleteMenuItem($itemId);
        return $this->json($response, ['status' => 'deleted']);
    }

    private function nsOk(Request $request, string $ns): bool
    {
        $tokenNs = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);
        return $tokenNs !== '' && $ns !== '' && $tokenNs === $ns;
    }

    private function pdoFromRequest(Request $request): PDO
    {
        $pdo = $this->pdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        return RequestDatabase::resolve($request);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
