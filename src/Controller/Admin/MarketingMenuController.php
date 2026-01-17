<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CmsMenuItem;
use App\Service\CmsPageMenuService;
use App\Service\PageService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Routing\RouteContext;

final class MarketingMenuController
{
    private const MAX_LABEL_LENGTH = 64;
    private const MAX_HREF_LENGTH = 2048;
    private const MAX_ICON_LENGTH = 64;
    private const MAX_LOCALE_LENGTH = 8;
    private const MAX_DETAIL_TITLE_LENGTH = 160;
    private const MAX_DETAIL_TEXT_LENGTH = 500;
    private const MAX_DETAIL_SUBLINE_LENGTH = 160;

    /** @var string[] */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];
    private const ALLOWED_LAYOUTS = ['link', 'dropdown', 'mega', 'column'];

    private CmsPageMenuService $menuService;
    private PageService $pageService;

    public function __construct(?CmsPageMenuService $menuService = null, ?PageService $pageService = null)
    {
        $this->menuService = $menuService ?? new CmsPageMenuService();
        $this->pageService = $pageService ?? new PageService();
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
        [$data, $errors] = $this->validatePayload($payload, $basePath);
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
     * @return array{array<string, mixed>, array<string, string>}
     */
    private function validatePayload(array $payload, string $basePath): array
    {
        $errors = [];

        $label = isset($payload['label']) ? trim((string) $payload['label']) : '';
        if ($label === '') {
            $errors['label'] = 'Label is required.';
        } elseif (mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            $errors['label'] = sprintf('Label must be at most %d characters.', self::MAX_LABEL_LENGTH);
        }

        $href = isset($payload['href']) ? trim((string) $payload['href']) : '';
        if ($href === '') {
            $errors['href'] = 'Href is required.';
        } elseif (mb_strlen($href) > self::MAX_HREF_LENGTH) {
            $errors['href'] = sprintf('Href must be at most %d characters.', self::MAX_HREF_LENGTH);
        } else {
            $hrefError = $this->validateHref($href, $basePath);
            if ($hrefError !== null) {
                $errors['href'] = $hrefError;
            }
        }

        $icon = isset($payload['icon']) ? trim((string) $payload['icon']) : null;
        if ($icon !== null && $icon !== '' && mb_strlen($icon) > self::MAX_ICON_LENGTH) {
            $errors['icon'] = sprintf('Icon must be at most %d characters.', self::MAX_ICON_LENGTH);
        }

        $layout = isset($payload['layout']) ? strtolower(trim((string) $payload['layout'])) : 'link';
        if (!in_array($layout, self::ALLOWED_LAYOUTS, true)) {
            $errors['layout'] = 'Layout is invalid.';
        }

        $parentId = isset($payload['parentId']) ? (int) $payload['parentId'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }

        $locale = isset($payload['locale']) ? strtolower(trim((string) $payload['locale'])) : null;
        if ($locale !== null && $locale !== '' && mb_strlen($locale) > self::MAX_LOCALE_LENGTH) {
            $errors['locale'] = sprintf('Locale must be at most %d characters.', self::MAX_LOCALE_LENGTH);
        }

        $detailTitle = isset($payload['detailTitle']) ? trim((string) $payload['detailTitle']) : null;
        if ($detailTitle !== null && $detailTitle !== '' && mb_strlen($detailTitle) > self::MAX_DETAIL_TITLE_LENGTH) {
            $errors['detailTitle'] = sprintf(
                'Detail title must be at most %d characters.',
                self::MAX_DETAIL_TITLE_LENGTH
            );
        }

        $detailText = isset($payload['detailText']) ? trim((string) $payload['detailText']) : null;
        if ($detailText !== null && $detailText !== '' && mb_strlen($detailText) > self::MAX_DETAIL_TEXT_LENGTH) {
            $errors['detailText'] = sprintf(
                'Detail text must be at most %d characters.',
                self::MAX_DETAIL_TEXT_LENGTH
            );
        }

        $detailSubline = isset($payload['detailSubline']) ? trim((string) $payload['detailSubline']) : null;
        if ($detailSubline !== null && $detailSubline !== '' && mb_strlen($detailSubline) > self::MAX_DETAIL_SUBLINE_LENGTH) {
            $errors['detailSubline'] = sprintf(
                'Detail subline must be at most %d characters.',
                self::MAX_DETAIL_SUBLINE_LENGTH
            );
        }

        $position = null;
        if (array_key_exists('position', $payload)) {
            $position = (int) $payload['position'];
            if ($position < 0) {
                $errors['position'] = 'Position must be zero or greater.';
            }
        }

        $data = [
            'id' => isset($payload['id']) ? (int) $payload['id'] : null,
            'label' => $label,
            'href' => $href,
            'icon' => $icon !== '' ? $icon : null,
            'parentId' => $parentId,
            'layout' => $layout,
            'position' => $position,
            'isExternal' => $this->normalizeBoolean($payload['isExternal'] ?? $payload['external'] ?? false),
            'locale' => $locale !== '' ? $locale : null,
            'isActive' => $this->normalizeBoolean($payload['isActive'] ?? true),
            'isStartpage' => $this->normalizeBoolean($payload['isStartpage'] ?? false),
            'detailTitle' => $detailTitle !== '' ? $detailTitle : null,
            'detailText' => $detailText !== '' ? $detailText : null,
            'detailSubline' => $detailSubline !== '' ? $detailSubline : null,
        ];

        return [$data, $errors];
    }

    private function validateHref(string $href, string $basePath): ?string
    {
        if (str_starts_with($href, '//')) {
            return 'Protocol-relative URLs are not allowed.';
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            $scheme = strtolower($scheme);
            if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
                return 'URL scheme is not allowed.';
            }

            return null;
        }

        $firstChar = $href[0] ?? '';
        if ($firstChar === '#' || $firstChar === '?') {
            return null;
        }

        if ($firstChar !== '/') {
            return 'Link must be basePath-relative.';
        }

        if ($basePath === '') {
            return null;
        }

        if ($href === $basePath || $href === $basePath . '/') {
            return null;
        }

        if (!str_starts_with($href, $basePath . '/')) {
            return 'Link must start with the basePath.';
        }

        return null;
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
}
