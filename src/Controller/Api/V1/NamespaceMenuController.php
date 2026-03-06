<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Service\CmsMenuDefinitionService;
use App\Service\CmsPageMenuService;
use App\Service\PageService;
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
        private readonly ?CmsMenuDefinitionService $defs = null,
        private readonly ?CmsPageMenuService $items = null,
    ) {
    }

    public function listMenus(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        $defs = $this->getDefs($request);
        $menus = $defs->listMenus($ns, false);

        $out = [];
        foreach ($menus as $m) {
            $out[] = [
                'id' => $m->getId(),
                'namespace' => $m->getNamespace(),
                'label' => $m->getLabel(),
                'locale' => $m->getLocale(),
                'isActive' => $m->isActive(),
                'updatedAt' => $m->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }

        return $this->json($response, ['namespace' => $ns, 'menus' => $out]);
    }

    public function createMenu(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = is_string($payload['label'] ?? null) ? trim((string) $payload['label']) : '';
        $locale = is_string($payload['locale'] ?? null) ? trim((string) $payload['locale']) : null;
        $isActive = array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true;

        if ($label === '') {
            return $this->json($response, ['error' => 'missing_label'], 422);
        }

        $defs = $this->getDefs($request);
        $menu = $defs->createMenu($ns, $label, $locale, $isActive);

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
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = is_string($payload['label'] ?? null) ? trim((string) $payload['label']) : '';
        $locale = is_string($payload['locale'] ?? null) ? trim((string) $payload['locale']) : null;
        $isActive = array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true;

        if ($label === '') {
            return $this->json($response, ['error' => 'missing_label'], 422);
        }

        $defs = $this->getDefs($request);
        $menu = $defs->updateMenu($ns, $menuId, $label, $locale, $isActive);

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
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $defs = $this->getDefs($request);
        $ok = $defs->deleteMenu($ns, $menuId);

        return $this->json($response, ['status' => $ok ? 'deleted' : 'not_found']);
    }

    public function listMenuItems(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $params = $request->getQueryParams();
        $locale = is_string($params['locale'] ?? null) ? (string) $params['locale'] : null;
        $onlyActive = array_key_exists('onlyActive', $params) ? (bool) $params['onlyActive'] : true;

        $defs = $this->getDefs($request);
        $items = $defs->getMenuItemsForMenu($ns, $menuId, $locale, $onlyActive);

        $out = [];
        foreach ($items as $it) {
            $out[] = [
                'id' => $it->getId(),
                'menuId' => $it->getMenuId(),
                'namespace' => $it->getNamespace(),
                'parentId' => $it->getParentId(),
                'label' => $it->getLabel(),
                'href' => $it->getHref(),
                'icon' => $it->getIcon(),
                'layout' => $it->getLayout(),
                'position' => $it->getPosition(),
                'isExternal' => $it->isExternal(),
                'locale' => $it->getLocale(),
                'isActive' => $it->isActive(),
                'isStartpage' => $it->isStartpage(),
            ];
        }

        return $this->json($response, ['namespace' => $ns, 'menuId' => $menuId, 'items' => $out]);
    }

    public function createMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        if ($menuId <= 0) {
            return $this->json($response, ['error' => 'invalid_menu_id'], 400);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = is_string($payload['label'] ?? null) ? (string) $payload['label'] : '';
        $href = is_string($payload['href'] ?? null) ? (string) $payload['href'] : '';
        if (trim($label) === '' || trim($href) === '') {
            return $this->json($response, ['error' => 'missing_label_or_href'], 422);
        }

        $svc = $this->getItems($request);
        $item = $svc->createMenuItemForMenu(
            $menuId,
            $ns,
            $label,
            $href,
            is_string($payload['icon'] ?? null) ? (string) $payload['icon'] : null,
            array_key_exists('parentId', $payload) ? ($payload['parentId'] === null ? null : (int) $payload['parentId']) : null,
            is_string($payload['layout'] ?? null) ? (string) $payload['layout'] : CmsPageMenuService::DEFAULT_LAYOUT,
            is_string($payload['detailTitle'] ?? null) ? (string) $payload['detailTitle'] : null,
            is_string($payload['detailText'] ?? null) ? (string) $payload['detailText'] : null,
            is_string($payload['detailSubline'] ?? null) ? (string) $payload['detailSubline'] : null,
            array_key_exists('position', $payload) ? (int) $payload['position'] : null,
            array_key_exists('isExternal', $payload) ? (bool) $payload['isExternal'] : false,
            is_string($payload['locale'] ?? null) ? (string) $payload['locale'] : null,
            array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true,
            array_key_exists('isStartpage', $payload) ? (bool) $payload['isStartpage'] : false,
        );

        return $this->json($response, ['status' => 'created', 'id' => $item->getId()], 201);
    }

    public function updateMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        $itemId = (int) ($args['itemId'] ?? 0);
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        if ($menuId <= 0 || $itemId <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $label = is_string($payload['label'] ?? null) ? (string) $payload['label'] : '';
        $href = is_string($payload['href'] ?? null) ? (string) $payload['href'] : '';
        if (trim($label) === '' || trim($href) === '') {
            return $this->json($response, ['error' => 'missing_label_or_href'], 422);
        }

        $svc = $this->getItems($request);
        $item = $svc->getMenuItemById($itemId);
        if ($item === null || $item->getMenuId() !== $menuId || $item->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        $updated = $svc->updateMenuItem(
            $itemId,
            $label,
            $href,
            is_string($payload['icon'] ?? null) ? (string) $payload['icon'] : null,
            array_key_exists('parentId', $payload) ? ($payload['parentId'] === null ? null : (int) $payload['parentId']) : null,
            is_string($payload['layout'] ?? null) ? (string) $payload['layout'] : CmsPageMenuService::DEFAULT_LAYOUT,
            is_string($payload['detailTitle'] ?? null) ? (string) $payload['detailTitle'] : null,
            is_string($payload['detailText'] ?? null) ? (string) $payload['detailText'] : null,
            is_string($payload['detailSubline'] ?? null) ? (string) $payload['detailSubline'] : null,
            array_key_exists('position', $payload) ? (int) $payload['position'] : null,
            array_key_exists('isExternal', $payload) ? (bool) $payload['isExternal'] : false,
            is_string($payload['locale'] ?? null) ? (string) $payload['locale'] : null,
            array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true,
            array_key_exists('isStartpage', $payload) ? (bool) $payload['isStartpage'] : false,
        );

        return $this->json($response, ['status' => 'updated', 'id' => $updated->getId()]);
    }

    public function deleteMenuItem(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        $menuId = (int) ($args['menuId'] ?? 0);
        $itemId = (int) ($args['itemId'] ?? 0);
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        if ($menuId <= 0 || $itemId <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $svc = $this->getItems($request);
        $item = $svc->getMenuItemById($itemId);
        if ($item === null || $item->getMenuId() !== $menuId || $item->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        $svc->deleteMenuItem($itemId);
        return $this->json($response, ['status' => 'deleted']);
    }

    public function listAssignments(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        $params = $request->getQueryParams();
        $menuId = isset($params['menuId']) ? (int) $params['menuId'] : null;
        $pageId = isset($params['pageId']) ? (int) $params['pageId'] : null;
        $slot = is_string($params['slot'] ?? null) ? (string) $params['slot'] : null;
        $locale = is_string($params['locale'] ?? null) ? (string) $params['locale'] : null;

        $defs = $this->getDefs($request);
        $assignments = $defs->listAssignments($ns, $menuId, $pageId, $slot, $locale, false);

        $out = [];
        foreach ($assignments as $a) {
            $out[] = [
                'id' => $a->getId(),
                'menuId' => $a->getMenuId(),
                'pageId' => $a->getPageId(),
                'namespace' => $a->getNamespace(),
                'slot' => $a->getSlot(),
                'locale' => $a->getLocale(),
                'isActive' => $a->isActive(),
            ];
        }

        return $this->json($response, ['namespace' => $ns, 'assignments' => $out]);
    }

    public function createAssignment(Request $request, Response $response, array $args): Response
    {
        $ns = (string) ($args['ns'] ?? '');
        if ($guard = $this->guardNamespace($request, $response, $ns)) {
            return $guard;
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $menuId = (int) ($payload['menuId'] ?? 0);
        $pageId = array_key_exists('pageId', $payload) ? ($payload['pageId'] === null ? null : (int) $payload['pageId']) : null;
        $slot = is_string($payload['slot'] ?? null) ? trim((string) $payload['slot']) : '';
        $locale = is_string($payload['locale'] ?? null) ? (string) $payload['locale'] : null;
        $isActive = array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true;

        if ($menuId <= 0 || $slot === '') {
            return $this->json($response, ['error' => 'invalid_payload'], 422);
        }

        $defs = $this->getDefs($request);
        $assignment = $defs->createAssignment($ns, $menuId, $pageId, $slot, $locale, $isActive);

        return $this->json($response, ['status' => 'created', 'id' => $assignment->getId()], 201);
    }

    private function guardNamespace(Request $request, Response $response, string $ns): ?Response
    {
        $tokenNs = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);
        if ($tokenNs === '' || $ns === '' || $tokenNs !== $ns) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        return null;
    }

    private function getDefs(Request $request): CmsMenuDefinitionService
    {
        $pdo = $this->pdo;
        if (!$pdo instanceof PDO) {
            $pdo = RequestDatabase::resolve($request);
        }

        return $this->defs ?? new CmsMenuDefinitionService($pdo);
    }

    private function getItems(Request $request): CmsPageMenuService
    {
        $pdo = $this->pdo;
        if (!$pdo instanceof PDO) {
            $pdo = RequestDatabase::resolve($request);
        }

        return $this->items ?? new CmsPageMenuService($pdo, new PageService($pdo));
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
