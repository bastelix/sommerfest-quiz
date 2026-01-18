<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CmsMenuItem;
use App\Service\CmsPageMenuService;
use App\Service\PageService;
use App\Support\BasePathHelper;
use App\Support\MarketingMenuItemValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Routing\RouteContext;

final class MarketingMenuController
{
    private CmsPageMenuService $menuService;
    private PageService $pageService;
    private MarketingMenuItemValidator $validator;

    public function __construct(
        ?CmsPageMenuService $menuService = null,
        ?PageService $pageService = null,
        ?MarketingMenuItemValidator $validator = null
    ) {
        $this->menuService = $menuService ?? new CmsPageMenuService();
        $this->pageService = $pageService ?? new PageService();
        $this->validator = $validator ?? new MarketingMenuItemValidator();
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        $page = $this->pageService->findById($pageId);
        if ($page === null) {
            return $this->jsonError($response, 'Page not found.', 404);
        }

        $params = $request->getQueryParams();
        $locale = null;
        if (array_key_exists('locale', $params) && is_string($params['locale'])) {
            $candidate = strtolower(trim($params['locale']));
            if ($candidate !== '') {
                $locale = $candidate;
            }
        }

        $items = $this->menuService->getMenuItemsForPage($pageId, $locale, false);
        $payload = [
            'page' => [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
            ],
            'items' => array_map(fn (CmsMenuItem $item): array => $this->serializeItem($item), $items),
        ];

        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function save(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        if ($this->pageService->findById($pageId) === null) {
            return $this->jsonError($response, 'Page not found.', 404);
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

        $itemId = $data['id'] ?? null;
        if ($itemId !== null) {
            $existing = $this->menuService->getMenuItemById($itemId);
            $menuId = $existing !== null
                ? $this->menuService->getMenuIdForPage($pageId, $data['locale'] ?? $existing->getLocale())
                : null;
            if ($existing === null || $menuId === null || $existing->getMenuId() !== $menuId) {
                return $this->jsonError($response, 'Menu item not found.', 404);
            }
        }

        try {
            if ($itemId === null) {
                $item = $this->menuService->createMenuItem(
                    $pageId,
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
                $status = 201;
            } else {
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
                $status = 200;
            }
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        $response->getBody()->write(json_encode(['item' => $this->serializeItem($item)]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        if ($this->pageService->findById($pageId) === null) {
            return $this->jsonError($response, 'Page not found.', 404);
        }

        $payload = $this->parseJsonBody($request) ?? $request->getQueryParams();
        $itemId = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($itemId <= 0) {
            return $this->jsonError($response, 'Menu item id is required.', 422);
        }

        $item = $this->menuService->getMenuItemById($itemId);
        $menuId = $item !== null ? $this->menuService->getMenuIdForPage($pageId, $item->getLocale()) : null;
        if ($item === null || $menuId === null || $item->getMenuId() !== $menuId) {
            return $this->jsonError($response, 'Menu item not found.', 404);
        }

        $this->menuService->deleteMenuItem($itemId);

        return $response->withStatus(204);
    }

    public function sort(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        if ($this->pageService->findById($pageId) === null) {
            return $this->jsonError($response, 'Page not found.', 404);
        }

        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $orderedIds = $payload['orderedItems'] ?? $payload['orderedIds'] ?? $payload['order'] ?? null;
        if (!is_array($orderedIds)) {
            return $this->jsonError($response, 'orderedIds must be an array.', 422);
        }

        try {
            $this->menuService->reorderMenuItems($pageId, $orderedIds);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        return $response->withStatus(204);
    }

    public function export(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        $page = $this->pageService->findById($pageId);
        if ($page === null) {
            return $this->jsonError($response, 'Page not found.', 404);
        }

        $params = $request->getQueryParams();
        $locale = null;
        if (isset($params['locale']) && is_string($params['locale'])) {
            $localeCandidate = strtolower(trim($params['locale']));
            if ($localeCandidate !== '') {
                $locale = $localeCandidate;
            }
        }

        try {
            $payload = $this->menuService->serializeMenuExport($pageId, $locale);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 400);
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $response->getBody()->write((string) $json);

        $filename = sprintf('marketing-menu-%s-%s.json', $page->getSlug(), date('Ymd-His'));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function import(Request $request, Response $response, array $args): Response
    {
        $pageId = (int) ($args['pageId'] ?? 0);
        if ($this->pageService->findById($pageId) === null) {
            return $this->jsonError($response, 'Page not found.', 404);
        }

        $payload = $this->parseJsonBody($request);
        if ($payload === null) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        try {
            $this->menuService->importMenuPayload($pageId, $payload);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 422);
        }

        return $response->withStatus(204);
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
}
