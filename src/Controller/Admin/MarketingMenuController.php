<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Service\MarketingMenuService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class MarketingMenuController
{
    private PDO $pdo;
    private MarketingMenuService $menuService;
    private PageService $pageService;
    private NamespaceResolver $namespaceResolver;

    public function __construct(
        ?PDO $pdo = null,
        ?MarketingMenuService $menuService = null,
        ?PageService $pageService = null,
        ?NamespaceResolver $namespaceResolver = null
    ) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->pageService = $pageService ?? new PageService($this->pdo);
        $this->menuService = $menuService ?? new MarketingMenuService($this->pdo, $this->pageService);
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    public function index(Request $request, Response $response): Response
    {
        $pageId = (int) ($request->getQueryParams()['pageId'] ?? 0);
        if ($pageId <= 0) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $page = $this->pageService->findById($pageId);
        if ($page === null || $page->getNamespace() !== $namespace) {
            return $response->withStatus(404);
        }

        $items = array_map(
            static fn ($item): array => [
                'id' => $item->getId(),
                'page_id' => $item->getPageId(),
                'label' => $item->getLabel(),
                'href' => $item->getHref(),
                'icon' => $item->getIcon(),
                'position' => $item->getPosition(),
                'is_external' => $item->isExternal(),
                'locale' => $item->getLocale(),
                'is_active' => $item->isActive(),
                'updated_at' => $item->getUpdatedAt()?->format(DATE_ATOM),
            ],
            $this->menuService->getMenuItemsForPage($pageId, null, false)
        );

        $payload = [
            'page_id' => $pageId,
            'items' => $items,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $pageId = (int) ($data['pageId'] ?? 0);
        $items = $data['items'] ?? null;
        if ($pageId <= 0 || !is_array($items)) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $page = $this->pageService->findById($pageId);
        if ($page === null || $page->getNamespace() !== $namespace) {
            return $response->withStatus(404);
        }

        $existing = $this->menuService->getMenuItemsForPage($pageId, null, false);
        $existingById = [];
        foreach ($existing as $item) {
            $existingById[$item->getId()] = $item;
        }

        $usedIds = [];

        $this->pdo->beginTransaction();

        try {
            foreach (array_values($items) as $position => $item) {
                if (!is_array($item)) {
                    throw new RuntimeException('Invalid menu payload.');
                }

                $label = isset($item['label']) ? (string) $item['label'] : '';
                $href = isset($item['href']) ? (string) $item['href'] : '';
                $icon = isset($item['icon']) ? (string) $item['icon'] : null;
                $locale = isset($item['locale']) ? (string) $item['locale'] : null;
                $isActive = isset($item['is_active'])
                    ? filter_var($item['is_active'], FILTER_VALIDATE_BOOLEAN)
                    : (isset($item['isActive']) ? filter_var($item['isActive'], FILTER_VALIDATE_BOOLEAN) : true);
                $isExternal = isset($item['is_external'])
                    ? filter_var($item['is_external'], FILTER_VALIDATE_BOOLEAN)
                    : (isset($item['isExternal']) ? filter_var($item['isExternal'], FILTER_VALIDATE_BOOLEAN) : false);

                $id = isset($item['id']) ? (int) $item['id'] : 0;
                if ($id > 0 && isset($existingById[$id])) {
                    $this->menuService->updateMenuItem(
                        $id,
                        $label,
                        $href,
                        $icon,
                        $position,
                        $isExternal,
                        $locale,
                        $isActive
                    );
                    $usedIds[] = $id;
                } else {
                    $created = $this->menuService->createMenuItem(
                        $pageId,
                        $label,
                        $href,
                        $icon,
                        $position,
                        $isExternal,
                        $locale,
                        $isActive
                    );
                    $usedIds[] = $created->getId();
                }
            }

            $unused = array_diff(array_keys($existingById), $usedIds);
            foreach ($unused as $id) {
                $this->menuService->deleteMenuItem((int) $id);
            }

            $this->pdo->commit();
        } catch (RuntimeException $exception) {
            $this->pdo->rollBack();
            return $response->withStatus(400);
        }

        return $response->withStatus(204);
    }
}
