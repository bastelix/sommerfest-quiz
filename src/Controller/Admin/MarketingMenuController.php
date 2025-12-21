<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\MarketingPageMenuItem;
use App\Service\MarketingMenuService;
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

    /** @var string[] */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private MarketingMenuService $menuService;
    private PageService $pageService;

    public function __construct(?MarketingMenuService $menuService = null, ?PageService $pageService = null)
    {
        $this->menuService = $menuService ?? new MarketingMenuService();
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
        $locale = isset($params['locale']) && is_string($params['locale']) ? strtolower(trim($params['locale'])) : null;

        $items = $this->menuService->getMenuItemsForPage($pageId, $locale, false);
        $payload = [
            'page' => [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
            ],
            'items' => array_map(fn (MarketingPageMenuItem $item): array => $this->serializeItem($item), $items),
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
            if ($existing === null || $existing->getPageId() !== $pageId) {
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
                    $data['position'],
                    $data['isExternal'],
                    $data['locale'],
                    $data['isActive']
                );
                $status = 201;
            } else {
                $item = $this->menuService->updateMenuItem(
                    $itemId,
                    $data['label'],
                    $data['href'],
                    $data['icon'],
                    $data['position'],
                    $data['isExternal'],
                    $data['locale'],
                    $data['isActive']
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
        if ($item === null || $item->getPageId() !== $pageId) {
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

        $orderedIds = $payload['orderedIds'] ?? $payload['order'] ?? null;
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

        $locale = isset($payload['locale']) ? strtolower(trim((string) $payload['locale'])) : null;
        if ($locale !== null && $locale !== '' && mb_strlen($locale) > self::MAX_LOCALE_LENGTH) {
            $errors['locale'] = sprintf('Locale must be at most %d characters.', self::MAX_LOCALE_LENGTH);
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
            'position' => $position,
            'isExternal' => $this->normalizeBoolean($payload['isExternal'] ?? $payload['external'] ?? false),
            'locale' => $locale !== '' ? $locale : null,
            'isActive' => $this->normalizeBoolean($payload['isActive'] ?? true),
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
    private function serializeItem(MarketingPageMenuItem $item): array
    {
        return [
            'id' => $item->getId(),
            'pageId' => $item->getPageId(),
            'namespace' => $item->getNamespace(),
            'label' => $item->getLabel(),
            'href' => $item->getHref(),
            'icon' => $item->getIcon(),
            'position' => $item->getPosition(),
            'isExternal' => $item->isExternal(),
            'locale' => $item->getLocale(),
            'isActive' => $item->isActive(),
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
