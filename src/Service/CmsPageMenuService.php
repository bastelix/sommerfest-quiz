<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\CmsMenuAssignment;
use App\Domain\CmsMenuItem;
use App\Domain\Page;
use App\Infrastructure\Database;
use App\Service\Marketing\MarketingMenuAiGenerator;
use App\Service\Marketing\MarketingMenuAiException;
use App\Service\Marketing\MarketingMenuAiTranslator;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

final class CmsPageMenuService
{
    private const DEFAULT_LAYOUT = 'link';
    private const ALLOWED_LAYOUTS = ['link', 'dropdown', 'mega', 'column'];
    private const ALLOWED_IMPORT_FIELDS = [
        'label',
        'href',
        'icon',
        'layout',
        'detailTitle',
        'detailText',
        'detailSubline',
        'position',
        'order',
        'isExternal',
        'locale',
        'isActive',
        'isStartpage',
        'children',
        'submenu',
        'link',
    ];

    private PDO $pdo;

    private PageService $pages;

    private CmsMenuDefinitionService $menuDefinitions;

    private MarketingMenuAiGenerator $menuAiGenerator;

    private MarketingMenuAiTranslator $menuAiTranslator;

    public function __construct(
        ?PDO $pdo = null,
        ?PageService $pages = null,
        ?CmsMenuDefinitionService $menuDefinitions = null,
        ?MarketingMenuAiGenerator $menuAiGenerator = null,
        ?MarketingMenuAiTranslator $menuAiTranslator = null
    ) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->pages = $pages ?? new PageService($this->pdo);
        $this->menuDefinitions = $menuDefinitions ?? new CmsMenuDefinitionService($this->pdo);
        $this->menuAiGenerator = $menuAiGenerator ?? new MarketingMenuAiGenerator();
        $this->menuAiTranslator = $menuAiTranslator ?? new MarketingMenuAiTranslator();
    }

    /**
     * Load menu items for a page slug with namespace fallback support.
     *
     * @return CmsMenuItem[]
     */
    public function getMenuItemsForSlug(
        string $namespace,
        string $slug,
        ?string $locale = null,
        bool $onlyActive = true
    ): array {
        $page = $this->resolvePageByKey($namespace, $slug);
        if ($page === null) {
            return [];
        }

        $this->ensureMenuItemsImported($page);

        $items = $this->fetchItemsForPage($page, $locale, $onlyActive);

        if ($items !== [] || $page->getNamespace() === PageService::DEFAULT_NAMESPACE) {
            return $items;
        }

        $fallbackPage = $this->pages->findByKey(PageService::DEFAULT_NAMESPACE, $page->getSlug());
        if ($fallbackPage === null) {
            return $items;
        }

        $this->ensureMenuItemsImported($fallbackPage);

        return $this->fetchItemsForPage($fallbackPage, $locale, $onlyActive);
    }

    /**
     * Load menu items for a page id.
     *
     * @return CmsMenuItem[]
     */
    public function getMenuItemsForPage(int $pageId, ?string $locale = null, bool $onlyActive = true): array
    {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            return [];
        }

        $this->ensureMenuItemsImported($page);

        return $this->fetchItemsForPage($page, $locale, $onlyActive);
    }

    public function getMenuIdForPage(int $pageId, ?string $locale = null): ?int
    {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            return null;
        }

        $menuContext = $this->resolveMenuContext($page, $locale);
        return $menuContext['menuId'] ?? null;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function serializeExportNode(array $item): array
    {
        $children = [];
        foreach ($item['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $children[] = $this->serializeExportNode($child);
        }

        return [
            'label' => (string) ($item['label'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'icon' => $this->normalizeIcon($item['icon'] ?? null),
            'layout' => isset($item['layout'])
                ? $this->normalizeLayout((string) $item['layout'])
                : self::DEFAULT_LAYOUT,
            'detailTitle' => $this->normalizeDetail($item['detailTitle'] ?? null),
            'detailText' => $this->normalizeDetail($item['detailText'] ?? null),
            'detailSubline' => $this->normalizeDetail($item['detailSubline'] ?? null),
            'position' => isset($item['position']) ? (int) $item['position'] : 0,
            'isExternal' => isset($item['isExternal']) ? $this->normalizeBoolean($item['isExternal']) : false,
            'locale' => isset($item['locale']) ? $this->normalizeLocale((string) $item['locale']) : 'de',
            'isActive' => isset($item['isActive']) ? $this->normalizeBoolean($item['isActive']) : true,
            'isStartpage' => isset($item['isStartpage']) ? $this->normalizeBoolean($item['isStartpage']) : false,
            'children' => $children,
        ];
    }

    /**
     * Export a page menu with nested children.
     *
     * @return array<string, mixed>
     */
    public function serializeMenuExport(int $pageId, ?string $locale = null): array
    {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            throw new RuntimeException('Page not found.');
        }

        $menuContext = $this->requireMenuContext($page, $locale);
        $menu = $this->menuDefinitions->getMenuById($page->getNamespace(), $menuContext['menuId']);

        $items = $this->getMenuItemsForPage($pageId, $locale, false);
        $tree = $this->buildMenuTree($items, false);

        return [
            'page' => [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'namespace' => $page->getNamespace(),
            ],
            'menu' => [
                'id' => $menuContext['menuId'],
                'label' => $menu?->getLabel(),
                'locale' => $menu?->getLocale(),
                'isActive' => $menu?->isActive(),
            ],
            'menuId' => $menuContext['menuId'],
            'namespace' => $page->getNamespace(),
            'items' => array_map(fn (array $item): array => $this->serializeExportNode($item), $tree),
        ];
    }

    /**
     * Import menu items from a serialized payload.
     *
     * @param array<string, mixed> $payload
     */
    public function importMenuPayload(int $pageId, array $payload): void
    {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            throw new RuntimeException('Page not found.');
        }

        $allowedKeys = ['items', 'namespace', 'page', 'allowNamespaceMismatch', 'menuId', 'menu', 'allowMenuMismatch'];
        $unknownKeys = array_diff(array_keys($payload), $allowedKeys);
        if ($unknownKeys !== []) {
            throw new RuntimeException(sprintf('Unerlaubte Felder im Payload: %s.', implode(', ', $unknownKeys)));
        }

        $namespace = isset($payload['namespace']) ? trim((string) $payload['namespace']) : $page->getNamespace();
        $allowNamespaceMismatch = isset($payload['allowNamespaceMismatch'])
            ? (bool) $payload['allowNamespaceMismatch']
            : true;
        if ($namespace !== '' && $namespace !== $page->getNamespace() && !$allowNamespaceMismatch) {
            throw new RuntimeException('Namespace des Exports stimmt nicht mit der Seite überein.');
        }

        $menuContext = $this->requireMenuContext($page, null);
        $payloadMenuId = null;
        if (isset($payload['menuId'])) {
            $payloadMenuId = (int) $payload['menuId'];
        } elseif (isset($payload['menu']) && is_array($payload['menu']) && isset($payload['menu']['id'])) {
            $payloadMenuId = (int) $payload['menu']['id'];
        }

        $allowMenuMismatch = isset($payload['allowMenuMismatch'])
            ? (bool) $payload['allowMenuMismatch']
            : false;

        if ($payloadMenuId !== null && $payloadMenuId > 0 && $payloadMenuId !== $menuContext['menuId'] && !$allowMenuMismatch) {
            throw new RuntimeException('Menu-ID des Exports stimmt nicht mit dem Zielmenü überein.');
        }

        if (!isset($payload['items']) || !is_array($payload['items'])) {
            throw new RuntimeException('items muss ein Array sein.');
        }

        $startpageLocales = [];
        $items = $this->normalizeImportItems($payload['items'], $startpageLocales);

        $this->pdo->beginTransaction();

        try {
            $this->resetStartpages($page->getNamespace());
            $this->deleteMenuItemsForMenu($menuContext['menuId'], $menuContext['namespace']);
            $this->persistImportedItems($menuContext['menuId'], $menuContext['namespace'], $items, null);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            $message = $this->formatImportError($exception);

            throw new RuntimeException($message, 0, $exception);
        }
    }

    /**
     * Generate menu entries from page HTML via AI and persist them.
     *
     * @return CmsMenuItem[]
     */
    public function generateMenuFromPage(Page $page, ?string $locale, bool $overwrite): array
    {
        $normalizedLocale = $locale !== null ? $this->normalizeLocale($locale) : null;
        $menuContext = $this->requireMenuContext($page, $normalizedLocale);
        $startpageLocales = $overwrite ? [] : $this->collectStartpageLocales($page, $normalizedLocale);

        $items = $this->menuAiGenerator->generate($page, $normalizedLocale);
        $normalizedItems = $this->normalizeImportItems($items, $startpageLocales);

        $this->pdo->beginTransaction();

        try {
            if ($overwrite) {
                $this->resetStartpages($page->getNamespace());
                $this->deleteMenuItemsForMenu($menuContext['menuId'], $menuContext['namespace']);
            }

            $positionOffset = $overwrite
                ? 0
                : $this->determineNextRootPosition(
                    $menuContext['menuId'],
                    $menuContext['namespace'],
                    $normalizedLocale
                );

            $itemsWithLocale = $this->applyLocaleAndPositions($normalizedItems, $normalizedLocale, $positionOffset);

            $this->persistImportedItems($menuContext['menuId'], $menuContext['namespace'], $itemsWithLocale, null);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            $errorMessage = $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'Menu generation failed.';
            $errorCode = $exception instanceof PDOException ? 'persistence_failed' : 'menu_generation_failed';
            $status = $exception instanceof PDOException ? 500 : 500;

            throw new MarketingMenuAiException($errorMessage, $errorCode, $status, $exception);
        }

        return $this->getMenuItemsForPage($page->getId(), $normalizedLocale, false);
    }

    /**
     * Translate an existing menu into another locale via AI and persist the result.
     *
     * @return CmsMenuItem[]
     */
    public function translateMenuFromLocale(
        Page $page,
        string $sourceLocale,
        string $targetLocale,
        bool $overwrite
    ): array {
        $normalizedSourceLocale = $this->normalizeLocale($sourceLocale);
        $normalizedTargetLocale = $this->normalizeLocale($targetLocale);

        if ($normalizedSourceLocale === $normalizedTargetLocale) {
            throw new RuntimeException('Source and target locale must differ.');
        }

        $export = $this->serializeMenuExport($page->getId(), $normalizedSourceLocale);
        $items = $export['items'] ?? [];
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('No menu items available for translation.');
        }

        $translatedItems = $this->menuAiTranslator->translate(
            $items,
            $normalizedSourceLocale,
            $normalizedTargetLocale
        );

        $menuContext = $this->requireMenuContext($page, $normalizedTargetLocale);
        $startpageLocales = $overwrite ? [] : $this->collectStartpageLocales($page, $normalizedTargetLocale);
        $normalizedItems = $this->normalizeImportItems($translatedItems, $startpageLocales);

        $this->pdo->beginTransaction();

        try {
            if ($overwrite) {
                $this->deleteMenuItemsForMenuAndLocale(
                    $menuContext['menuId'],
                    $menuContext['namespace'],
                    $normalizedTargetLocale
                );
            }

            $positionOffset = $overwrite
                ? 0
                : $this->determineNextRootPosition(
                    $menuContext['menuId'],
                    $menuContext['namespace'],
                    $normalizedTargetLocale
                );

            $itemsWithLocale = $this->applyLocaleAndPositions(
                $normalizedItems,
                $normalizedTargetLocale,
                $positionOffset
            );

            $this->persistImportedItems($menuContext['menuId'], $menuContext['namespace'], $itemsWithLocale, null);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            throw new RuntimeException('Menu translation failed.', 0, $exception);
        }

        return $this->getMenuItemsForPage($page->getId(), $normalizedTargetLocale, false);
    }

    /**
     * Load menu items as a nested tree for a page slug.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMenuTreeForSlug(
        string $namespace,
        string $slug,
        ?string $locale = null,
        bool $onlyActive = true
    ): array {
        $items = $this->getMenuItemsForSlug($namespace, $slug, $locale, $onlyActive);

        return $this->buildMenuTree($items, $onlyActive);
    }

    /**
     * Resolve the startpage slug for the given namespace and locale.
     */
    public function resolveStartpageSlug(string $namespace, ?string $locale = null): ?string
    {
        $item = $this->resolveStartpage($namespace, $locale);
        if ($item === null) {
            return null;
        }

        $page = $this->resolvePageFromMenuItem($item);
        if ($page === null) {
            return null;
        }

        return $page->getSlug();
    }

    public function resolveStartpage(
        string $namespace,
        ?string $locale = null,
        bool $requireExplicit = false
    ): ?CmsMenuItem {
        $normalizedNamespace = trim($namespace);
        if ($normalizedNamespace === '') {
            return null;
        }

        $normalizedLocale = $this->normalizeLocale($locale);
        $item = $this->fetchStartpageMenuItem($normalizedNamespace, $normalizedLocale, true);
        if ($item === null && $normalizedLocale !== 'de') {
            $item = $this->fetchStartpageMenuItem($normalizedNamespace, 'de', true);
        }

        if ($item === null && $requireExplicit) {
            return null;
        }

        if ($item === null) {
            $item = $this->fetchStartpageMenuItem($normalizedNamespace, $normalizedLocale, false);
        }
        if ($item === null && $normalizedLocale !== 'de') {
            $item = $this->fetchStartpageMenuItem($normalizedNamespace, 'de', false);
        }

        return $item;
    }

    public function markPageAsStartpage(int $pageId, ?string $locale = null, ?string $expectedNamespace = null): void
    {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            throw new RuntimeException('Page not found.');
        }

        if ($expectedNamespace !== null && $page->getNamespace() !== $expectedNamespace) {
            throw new RuntimeException('Startpage namespace does not match current domain.');
        }

        $this->ensureMenuItemsImported($page);
        $normalizedLocale = $locale !== null ? $this->normalizeLocale($locale) : null;
        $menuContext = $this->requireMenuContext($page, $normalizedLocale);
        $candidateHrefs = $this->buildPageHrefCandidates($page->getSlug());
        if ($candidateHrefs === []) {
            throw new RuntimeException('Startpage requires a page slug.');
        }

        $this->pdo->beginTransaction();

        try {
            $this->resetStartpages($page->getNamespace());

            $placeholders = implode(', ', array_fill(0, count($candidateHrefs), '?'));
            $sql = 'UPDATE marketing_menu_items SET is_startpage = TRUE WHERE menu_id = ? AND namespace = ?'
                . ' AND is_external = FALSE';
            $params = [$menuContext['menuId'], $page->getNamespace()];

            if ($normalizedLocale !== null) {
                $sql .= ' AND locale = ?';
                $params[] = $normalizedLocale;
            }

            $sql .= ' AND href IN (' . $placeholders . ')';
            $params = array_merge($params, $candidateHrefs);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ((int) $stmt->rowCount() === 0) {
                throw new RuntimeException('No menu item found for the selected startpage.');
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException(
                'Setting startpage failed.',
                0,
                $exception
            );
        }
    }

    public function clearStartpagesForNamespace(string $namespace): void
    {
        $this->resetStartpages($namespace);
    }

    /**
     * Fetch a single menu item by its id.
     */
    public function getMenuItemById(int $id): ?CmsMenuItem
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM marketing_menu_items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateItem($row);
    }

    /**
     * Create a new menu item for the given page.
     */
    public function createMenuItem(
        int $pageId,
        string $label,
        string $href,
        ?string $icon = null,
        ?int $parentId = null,
        string $layout = self::DEFAULT_LAYOUT,
        ?string $detailTitle = null,
        ?string $detailText = null,
        ?string $detailSubline = null,
        ?int $position = null,
        bool $isExternal = false,
        ?string $locale = null,
        bool $isActive = true,
        bool $isStartpage = false
    ): CmsMenuItem {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            throw new RuntimeException('Page not found.');
        }

        $menuContext = $this->requireMenuContext($page, $locale);
        $parent = $this->normalizeParent($menuContext['menuId'], $parentId);
        $normalizedLabel = $this->normalizeLabel($label);
        $normalizedHref = $this->normalizeHref($href);
        $normalizedIcon = $this->normalizeIcon($icon);
        $normalizedLayout = $this->normalizeLayout($layout);
        $normalizedDetailTitle = $this->normalizeDetail($detailTitle);
        $normalizedDetailText = $this->normalizeDetail($detailText);
        $normalizedDetailSubline = $this->normalizeDetail($detailSubline);
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedPosition = $position
            ?? $this->determineNextPosition(
                $menuContext['menuId'],
                $menuContext['namespace'],
                $normalizedLocale,
                $parent?->getId()
            );

        $this->pdo->beginTransaction();

        try {
            if ($isStartpage) {
                $this->resetStartpages($menuContext['namespace']);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO marketing_menu_items (menu_id, namespace, parent_id, label, href, icon, layout, '
                . 'detail_title, detail_text, detail_subline, position, is_external, locale, is_active, '
                . 'is_startpage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $menuContext['menuId'],
                $menuContext['namespace'],
                $parent?->getId(),
                $normalizedLabel,
                $normalizedHref,
                $normalizedIcon,
                $normalizedLayout,
                $normalizedDetailTitle,
                $normalizedDetailText,
                $normalizedDetailSubline,
                $normalizedPosition,
                $isExternal ? 1 : 0,
                $normalizedLocale,
                $isActive ? 1 : 0,
                $isStartpage ? 1 : 0,
            ]);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Creating menu item failed.', 0, $exception);
        }

        $itemId = (int) $this->pdo->lastInsertId();
        $item = $this->getMenuItemById($itemId);
        if ($item === null) {
            throw new RuntimeException('Menu item could not be loaded after creation.');
        }

        return $item;
    }

    /**
     * Create a menu item directly for a menu definition.
     */
    public function createMenuItemForMenu(
        int $menuId,
        string $namespace,
        string $label,
        string $href,
        ?string $icon,
        ?int $parentId,
        string $layout,
        ?string $detailTitle,
        ?string $detailText,
        ?string $detailSubline,
        ?int $position,
        bool $isExternal,
        ?string $locale,
        bool $isActive,
        bool $isStartpage
    ): CmsMenuItem {
        $parent = $this->normalizeParent($menuId, $parentId);
        $normalizedLabel = $this->normalizeLabel($label);
        $normalizedHref = $this->normalizeHref($href);
        $normalizedIcon = $this->normalizeIcon($icon);
        $normalizedLayout = $this->normalizeLayout($layout);
        $normalizedDetailTitle = $this->normalizeDetail($detailTitle);
        $normalizedDetailText = $this->normalizeDetail($detailText);
        $normalizedDetailSubline = $this->normalizeDetail($detailSubline);
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedPosition = $position
            ?? $this->determineNextPosition($menuId, $namespace, $normalizedLocale, $parent?->getId());

        $stmt = $this->pdo->prepare(
            'INSERT INTO marketing_menu_items (menu_id, namespace, parent_id, label, href, icon, layout, '
            . 'detail_title, detail_text, detail_subline, position, is_external, locale, is_active, is_startpage) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $menuId,
            $namespace,
            $parent?->getId(),
            $normalizedLabel,
            $normalizedHref,
            $normalizedIcon,
            $normalizedLayout,
            $normalizedDetailTitle,
            $normalizedDetailText,
            $normalizedDetailSubline,
            $normalizedPosition,
            $isExternal ? 1 : 0,
            $normalizedLocale,
            $isActive ? 1 : 0,
            $isStartpage ? 1 : 0,
        ]);

        $itemId = (int) $this->pdo->lastInsertId();
        $item = $this->getMenuItemById($itemId);
        if ($item === null) {
            throw new RuntimeException('Menu item could not be loaded after creation.');
        }

        return $item;
    }

    /**
     * Update an existing menu item.
     */
    public function updateMenuItem(
        int $id,
        string $label,
        string $href,
        ?string $icon = null,
        ?int $parentId = null,
        string $layout = self::DEFAULT_LAYOUT,
        ?string $detailTitle = null,
        ?string $detailText = null,
        ?string $detailSubline = null,
        ?int $position = null,
        bool $isExternal = false,
        ?string $locale = null,
        bool $isActive = true,
        bool $isStartpage = false
    ): CmsMenuItem {
        $existing = $this->getMenuItemById($id);
        if ($existing === null) {
            throw new RuntimeException('Menu item not found.');
        }

        $parent = $this->normalizeParent($existing->getMenuId(), $parentId, $id);
        $normalizedLabel = $this->normalizeLabel($label);
        $normalizedHref = $this->normalizeHref($href);
        $normalizedIcon = $this->normalizeIcon($icon);
        $normalizedLayout = $this->normalizeLayout($layout);
        $normalizedDetailTitle = $this->normalizeDetail($detailTitle);
        $normalizedDetailText = $this->normalizeDetail($detailText);
        $normalizedDetailSubline = $this->normalizeDetail($detailSubline);
        $normalizedLocale = $this->normalizeLocale($locale ?? $existing->getLocale());
        $normalizedPosition = $position ?? $existing->getPosition();

        $this->pdo->beginTransaction();

        try {
            if ($isStartpage) {
                $this->resetStartpages($existing->getNamespace());
            }

            $stmt = $this->pdo->prepare(
                'UPDATE marketing_menu_items SET parent_id = ?, label = ?, href = ?, icon = ?, layout = ?, '
                . 'detail_title = ?, detail_text = ?, detail_subline = ?, position = ?, is_external = ?, '
                . 'locale = ?, is_active = ?, is_startpage = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
            );

            $stmt->execute([
                $parent?->getId(),
                $normalizedLabel,
                $normalizedHref,
                $normalizedIcon,
                $normalizedLayout,
                $normalizedDetailTitle,
                $normalizedDetailText,
                $normalizedDetailSubline,
                $normalizedPosition,
                $isExternal ? 1 : 0,
                $normalizedLocale,
                $isActive ? 1 : 0,
                $isStartpage ? 1 : 0,
                $id,
            ]);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Updating menu item failed.', 0, $exception);
        }

        $item = $this->getMenuItemById($id);
        if ($item === null) {
            throw new RuntimeException('Menu item could not be loaded after update.');
        }

        return $item;
    }

    /**
     * Delete a menu item by id.
     */
    public function deleteMenuItem(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM marketing_menu_items WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param list<int|array<string, mixed>> $orderedIds
     */
    public function reorderMenuItems(int $pageId, array $orderedIds): void
    {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            throw new RuntimeException('Page not found.');
        }

        $menuContext = $this->requireMenuContext($page, null);
        if ($orderedIds === []) {
            return;
        }

        $orderedIds = array_values($orderedIds);
        $firstEntry = $orderedIds[0];

        if (is_int($firstEntry)) {
            $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));
            $existingIds = $this->getMenuItemIdsForMenu($menuContext['menuId'], $menuContext['namespace']);

            if ($existingIds === []) {
                return;
            }

            $missing = array_diff($orderedIds, $existingIds);
            if ($missing !== []) {
                throw new RuntimeException('Unknown menu item id(s) for menu: ' . implode(', ', $missing));
            }

            $this->pdo->beginTransaction();

            try {
                $update = $this->pdo->prepare(
                    'UPDATE marketing_menu_items SET position = ? WHERE menu_id = ? AND id = ?'
                );
                $position = 0;

                foreach ($orderedIds as $orderedId) {
                    $update->execute([$position, $menuContext['menuId'], $orderedId]);
                    $position++;
                }

                foreach ($existingIds as $itemId) {
                    if (in_array($itemId, $orderedIds, true)) {
                        continue;
                    }

                    $update->execute([$position, $menuContext['menuId'], $itemId]);
                    $position++;
                }

                $this->pdo->commit();
            } catch (PDOException $exception) {
                $this->pdo->rollBack();
                throw new RuntimeException(
                    'Updating menu order failed: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        }

        $normalizedItems = [];
        foreach ($orderedIds as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = isset($entry['id']) ? (int) $entry['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $position = isset($entry['position']) ? (int) $entry['position'] : null;
            if ($position === null || $position < 0) {
                continue;
            }
            $normalizedItems[$id] = $position;
        }

        if ($normalizedItems === []) {
            return;
        }

        $existingIds = $this->getMenuItemIdsForMenu($menuContext['menuId'], $menuContext['namespace']);
        $missing = array_diff(array_keys($normalizedItems), $existingIds);
        if ($missing !== []) {
            throw new RuntimeException('Unknown menu item id(s) for menu: ' . implode(', ', $missing));
        }

        $this->pdo->beginTransaction();

        try {
            $update = $this->pdo->prepare(
                'UPDATE marketing_menu_items SET position = ? WHERE menu_id = ? AND id = ?'
            );

            foreach ($normalizedItems as $id => $position) {
                $update->execute([$position, $menuContext['menuId'], $id]);
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Updating menu order failed: ' . $exception->getMessage(), 0, $exception);
        }

        return;
    }

    /**
     * @return CmsMenuItem[]
     */
    private function fetchItemsForMenuId(
        int $menuId,
        string $namespace,
        ?string $locale,
        bool $onlyActive
    ): array {
        $normalizedLocale = null;
        if ($locale !== null) {
            $candidate = strtolower(trim($locale));
            if ($candidate !== '') {
                $normalizedLocale = $candidate;
            }
        }
        $params = [$menuId, $namespace];
        $sql = 'SELECT * FROM marketing_menu_items WHERE menu_id = ? AND namespace = ?';

        if ($normalizedLocale !== null) {
            $sql .= ' AND locale = ?';
            $params[] = $normalizedLocale;
        }

        if ($onlyActive) {
            $sql .= ' AND is_active = TRUE';
        }

        $sql .= ' ORDER BY position ASC, id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = [];

        foreach ($rows as $row) {
            $items[] = $this->hydrateItem($row);
        }

        return $items;
    }

    /**
     * @return CmsMenuItem[]
     */
    private function fetchItemsForPage(Page $page, ?string $locale, bool $onlyActive): array
    {
        $menuContext = $this->resolveMenuContext($page, $locale);
        if ($menuContext === null) {
            return [];
        }

        return $this->fetchItemsForMenuId($menuContext['menuId'], $menuContext['namespace'], $locale, $onlyActive);
    }

    private function resolvePageByKey(string $namespace, string $slug): ?Page
    {
        $normalizedNamespace = trim($namespace);
        $normalizedSlug = trim($slug);

        if ($normalizedNamespace === '' || $normalizedSlug === '') {
            return null;
        }

        $page = $this->pages->findByKey($normalizedNamespace, $normalizedSlug);
        if ($page !== null) {
            return $page;
        }

        if ($normalizedNamespace === PageService::DEFAULT_NAMESPACE) {
            return null;
        }

        return $this->pages->findByKey(PageService::DEFAULT_NAMESPACE, $normalizedSlug);
    }

    private function resolvePageFromMenuItem(CmsMenuItem $item): ?Page
    {
        if ($item->isExternal()) {
            return null;
        }

        $href = trim($item->getHref());
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, '?')) {
            return null;
        }

        $path = parse_url($href, PHP_URL_PATH);
        $slug = is_string($path) ? trim($path, '/') : trim($href, '/');
        if ($slug === '') {
            return null;
        }

        return $this->resolvePageByKey($item->getNamespace(), $slug);
    }

    /**
     * @return string[]
     */
    private function buildPageHrefCandidates(string $slug): array
    {
        $normalizedSlug = trim($slug);
        if ($normalizedSlug === '') {
            return [];
        }

        $candidateWithSlash = '/' . ltrim($normalizedSlug, '/');

        $candidates = array_unique([
            $normalizedSlug,
            $candidateWithSlash,
        ]);

        return array_values($candidates);
    }

    /**
     * @return array{menuId: int, namespace: string}|null
     */
    private function resolveMenuContext(Page $page, ?string $locale): ?array
    {
        $normalizedLocale = $locale !== null ? $this->normalizeLocale($locale) : null;
        $assignments = $this->menuDefinitions->getAssignmentsForPage(
            $page->getNamespace(),
            $page->getId(),
            $normalizedLocale,
            true
        );

        if ($assignments === []) {
            return null;
        }

        $this->sortAssignments($assignments);
        $assignment = $assignments[0] ?? null;
        if ($assignment === null) {
            return null;
        }

        $menu = $this->menuDefinitions->getMenuById($page->getNamespace(), $assignment->getMenuId());
        if ($menu === null) {
            return null;
        }

        return [
            'menuId' => $menu->getId(),
            'namespace' => $page->getNamespace(),
        ];
    }

    /**
     * @return array{menuId: int, namespace: string}
     */
    private function requireMenuContext(Page $page, ?string $locale): array
    {
        $menuContext = $this->resolveMenuContext($page, $locale);
        if ($menuContext === null) {
            throw new RuntimeException('Menu assignment not found for the selected page.');
        }

        return $menuContext;
    }

    /**
     * @param CmsMenuAssignment[] $assignments
     */
    private function sortAssignments(array &$assignments): void
    {
        usort($assignments, static function (CmsMenuAssignment $a, CmsMenuAssignment $b): int {
            $localeOrder = strcmp($a->getLocale(), $b->getLocale());
            if ($localeOrder !== 0) {
                if ($a->getLocale() === 'de') {
                    return -1;
                }
                if ($b->getLocale() === 'de') {
                    return 1;
                }
                return $localeOrder;
            }

            $slotOrder = strcmp($a->getSlot(), $b->getSlot());
            if ($slotOrder !== 0) {
                return $slotOrder;
            }

            return $a->getId() <=> $b->getId();
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateItem(array $row): CmsMenuItem
    {
        $updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable((string) $row['updated_at'])
            : null;

        return new CmsMenuItem(
            (int) $row['id'],
            (int) $row['menu_id'],
            isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            (string) $row['namespace'],
            (string) $row['label'],
            (string) $row['href'],
            isset($row['icon']) ? (string) $row['icon'] : null,
            (int) ($row['position'] ?? 0),
            (bool) ($row['is_external'] ?? false),
            (string) ($row['locale'] ?? 'de'),
            (bool) ($row['is_active'] ?? true),
            isset($row['layout']) ? (string) $row['layout'] : self::DEFAULT_LAYOUT,
            isset($row['detail_title']) ? (string) $row['detail_title'] : null,
            isset($row['detail_text']) ? (string) $row['detail_text'] : null,
            isset($row['detail_subline']) ? (string) $row['detail_subline'] : null,
            (bool) ($row['is_startpage'] ?? false),
            $updatedAt
        );
    }

    private function determineNextPosition(int $menuId, string $namespace, string $locale, ?int $parentId): int
    {
        $sql = 'SELECT MAX(position) FROM marketing_menu_items WHERE menu_id = ? AND namespace = ? AND locale = ?';
        $params = [$menuId, $namespace, $locale];

        if ($parentId === null) {
            $sql .= ' AND parent_id IS NULL';
        } else {
            $sql .= ' AND parent_id = ?';
            $params[] = $parentId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $max = $stmt->fetchColumn();

        if (!is_numeric($max)) {
            return 0;
        }

        return ((int) $max) + 1;
    }

    private function determineNextRootPosition(int $menuId, string $namespace, ?string $locale): int
    {
        $items = $this->fetchItemsForMenuId($menuId, $namespace, $locale, false);
        $max = -1;
        foreach ($items as $item) {
            if ($item->getParentId() === null) {
                $max = max($max, $item->getPosition());
            }
        }

        return $max + 1;
    }

    private function collectStartpageLocales(Page $page, ?string $locale): array
    {
        $items = $this->getMenuItemsForPage($page->getId(), $locale, false);
        $locales = [];

        foreach ($items as $item) {
            if ($item->isStartpage()) {
                $locales[$item->getLocale()] = true;
            }
        }

        return $locales;
    }

    private function normalizeLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            throw new RuntimeException('Menu label is required.');
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalizeBoolean($value): bool
    {
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'false' || $normalized === '0') {
                return false;
            }
            if ($normalized === 'true' || $normalized === '1') {
                return true;
            }
        }

        return (bool) $value;
    }

    private function normalizeHref(string $href): string
    {
        $normalized = trim($href);
        if ($normalized === '') {
            throw new RuntimeException('Menu href is required.');
        }

        return $normalized;
    }

    private function normalizeIcon(?string $icon): ?string
    {
        if ($icon === null) {
            return null;
        }

        $normalized = trim($icon);
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeLocale(?string $locale): string
    {
        $normalized = strtolower(trim((string) $locale));
        return $normalized !== '' ? $normalized : 'de';
    }

    /**
     * @return int[]
     */
    private function getMenuItemIdsForMenu(int $menuId, string $namespace): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM marketing_menu_items WHERE menu_id = ? AND namespace = ? ORDER BY position ASC, id ASC'
        );
        $stmt->execute([$menuId, $namespace]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map(static fn ($id) => (int) $id, $ids);
    }

    private function normalizeLayout(string $layout): string
    {
        $normalized = strtolower(trim($layout));
        if (!in_array($normalized, self::ALLOWED_LAYOUTS, true)) {
            return self::DEFAULT_LAYOUT;
        }

        return $normalized;
    }

    private function normalizeDetail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeParent(int $menuId, ?int $parentId, ?int $itemId = null): ?CmsMenuItem
    {
        if ($parentId === null || $parentId <= 0) {
            return null;
        }

        if ($itemId !== null && $parentId === $itemId) {
            throw new RuntimeException('Menu item cannot be its own parent.');
        }

        $parent = $this->getMenuItemById($parentId);
        if ($parent === null || $parent->getMenuId() !== $menuId) {
            throw new RuntimeException('Parent menu item not found.');
        }

        return $parent;
    }

    private function resetStartpages(string $namespace): void
    {
        $normalizedNamespace = trim($namespace);
        if ($normalizedNamespace === '') {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE marketing_menu_items SET is_startpage = FALSE WHERE namespace = ?'
        );
        $stmt->execute([$normalizedNamespace]);
    }

    private function deleteMenuItemsForMenuAndLocale(int $menuId, string $namespace, string $locale): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM marketing_menu_items WHERE menu_id = ? AND namespace = ? AND locale = ?'
        );
        $stmt->execute([$menuId, $namespace, $locale]);
    }

    private function deleteMenuItemsForMenu(int $menuId, string $namespace): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM marketing_menu_items WHERE menu_id = ? AND namespace = ?');
        $stmt->execute([$menuId, $namespace]);
    }

    /**
     * @param CmsMenuItem[] $items
     * @return array<int, array<string, mixed>>
     */
    private function buildMenuTree(array $items, bool $applyStartpageFallback = false): array
    {
        $fallbackStartpageId = $applyStartpageFallback ? $this->resolveFallbackStartpageId($items) : null;
        $knownIds = [];
        foreach ($items as $item) {
            $knownIds[$item->getId()] = true;
        }

        $grouped = [];
        foreach ($items as $item) {
            $parentKey = $item->getParentId();
            if ($parentKey === null || !isset($knownIds[$parentKey])) {
                $parentKey = 0;
            }
            $grouped[$parentKey][] = $item;
        }

        foreach ($grouped as &$group) {
            usort($group, static function (CmsMenuItem $a, CmsMenuItem $b): int {
                if ($a->getPosition() === $b->getPosition()) {
                    return $a->getId() <=> $b->getId();
                }
                return $a->getPosition() <=> $b->getPosition();
            });
        }
        unset($group);

        $build = function (int $parentKey) use (&$build, $grouped, $fallbackStartpageId): array {
            $nodes = [];
            foreach ($grouped[$parentKey] ?? [] as $item) {
                $nodes[] = [
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
                    'isExternal' => $item->isExternal(),
                    'locale' => $item->getLocale(),
                    'isActive' => $item->isActive(),
                    'isStartpage' => $item->isStartpage()
                        || ($fallbackStartpageId !== null && $item->getId() === $fallbackStartpageId),
                    'children' => $build($item->getId()),
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    /**
     * @param array<int, mixed> $items
     * @param array<string, bool> $startpageLocales
     * @return array<int, array<string, mixed>>
     */
    private function normalizeImportItems(array $items, array &$startpageLocales, string $path = 'items'): array
    {
        $normalized = [];
        $positionCounter = 0;

        foreach ($items as $index => $item) {
            $currentPath = sprintf('%s[%d]', $path, $index);
            if (!is_array($item)) {
                throw new RuntimeException(sprintf('Ungültiger Menüeintrag bei %s.', $currentPath));
            }

            $unknownFields = array_diff(array_keys($item), self::ALLOWED_IMPORT_FIELDS);
            if ($unknownFields !== []) {
                throw new RuntimeException(sprintf(
                    'Unerlaubte Felder (%s) in %s.',
                    implode(', ', $unknownFields),
                    $currentPath
                ));
            }

            $hrefFields = array_intersect(array_keys($item), ['href', 'link']);
            if (count($hrefFields) > 1) {
                throw new RuntimeException(sprintf('Mischschema für Link-Felder in %s nicht erlaubt.', $currentPath));
            }

            $childrenFields = array_intersect(array_keys($item), ['children', 'submenu']);
            if (count($childrenFields) > 1) {
                throw new RuntimeException(sprintf('Mischschema für Children/Submenu in %s nicht erlaubt.', $currentPath));
            }

            $positionFields = array_intersect(array_keys($item), ['position', 'order']);
            if (count($positionFields) > 1) {
                throw new RuntimeException(sprintf('Mischschema für Position/Order in %s nicht erlaubt.', $currentPath));
            }

            $position = isset($item['position']) && is_numeric($item['position'])
                ? (int) $item['position']
                : (isset($item['order']) && is_numeric($item['order'])
                    ? (int) $item['order']
                    : $positionCounter);
            $positionCounter = max($positionCounter, $position + 1);

            $locale = isset($item['locale']) ? $this->normalizeLocale((string) $item['locale']) : 'de';
            $isStartpage = isset($item['isStartpage']) ? $this->normalizeBoolean($item['isStartpage']) : false;
            if ($isStartpage) {
                if (isset($startpageLocales[$locale])) {
                    throw new RuntimeException(sprintf('Mehr als eine Startpage für Locale %s.', $locale));
                }
                $startpageLocales[$locale] = true;
            }

            $children = [];
            if (array_key_exists('children', $item) || array_key_exists('submenu', $item)) {
                $rawChildren = $item['children'] ?? $item['submenu'] ?? [];
                if (!is_array($rawChildren)) {
                    throw new RuntimeException(sprintf('children/submenu muss ein Array sein (%s).', $currentPath));
                }
                $children = $this->normalizeImportItems(
                    $rawChildren,
                    $startpageLocales,
                    $currentPath . '.children'
                );
            }

            $href = $item['href'] ?? $item['link'] ?? '';

            $normalized[] = [
                'label' => $this->normalizeLabel((string) ($item['label'] ?? '')),
                'href' => $this->normalizeHref((string) $href),
                'icon' => array_key_exists('icon', $item)
                    ? $this->normalizeIcon($item['icon'] !== null ? (string) $item['icon'] : null)
                    : null,
                'layout' => isset($item['layout'])
                    ? $this->normalizeLayout((string) $item['layout'])
                    : self::DEFAULT_LAYOUT,
                'detailTitle' => $this->normalizeDetail($item['detailTitle'] ?? null),
                'detailText' => $this->normalizeDetail($item['detailText'] ?? null),
                'detailSubline' => $this->normalizeDetail($item['detailSubline'] ?? null),
                'position' => $position,
                'isExternal' => isset($item['isExternal']) ? $this->normalizeBoolean($item['isExternal']) : false,
                'locale' => $locale,
                'isActive' => isset($item['isActive']) ? $this->normalizeBoolean($item['isActive']) : true,
                'isStartpage' => $isStartpage,
                'children' => $children,
            ];
        }

        return $normalized;
    }

    private function formatImportError(\Throwable $exception): string
    {
        $messages = [];
        $current = $exception;

        while ($current !== null) {
            $message = trim($current->getMessage());
            if ($message !== '') {
                $messages[] = $message;
            }

            $current = $current->getPrevious();
        }

        $uniqueMessages = array_values(array_unique($messages));
        $details = $uniqueMessages !== [] ? sprintf(': %s', implode(' | ', $uniqueMessages)) : '';

        return 'Menu import failed' . $details;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function applyLocaleAndPositions(array $items, ?string $locale, int $rootOffset): array
    {
        $position = $rootOffset;
        $normalized = [];

        foreach ($items as $item) {
            $current = $item;
            if ($locale !== null) {
                $current['locale'] = $locale;
            }

            $current['position'] = ($item['position'] ?? 0) + $position;
            $position = $current['position'] + 1;

            if (isset($item['children']) && is_array($item['children'])) {
                $current['children'] = $this->applyLocaleAndPositions($item['children'], $locale, 0);
            }

            $normalized[] = $current;
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function persistImportedItems(
        int $menuId,
        string $namespace,
        array $items,
        ?int $parentId
    ): void {
        foreach ($items as $item) {
            $entity = $this->createMenuItemForMenu(
                $menuId,
                $namespace,
                $item['label'],
                $item['href'],
                $item['icon'],
                $parentId,
                $item['layout'],
                $item['detailTitle'],
                $item['detailText'],
                $item['detailSubline'],
                $item['position'],
                $item['isExternal'],
                $item['locale'],
                $item['isActive'],
                $item['isStartpage']
            );

            if ($item['children'] !== []) {
                $this->persistImportedItems($menuId, $namespace, $item['children'], $entity->getId());
            }
        }
    }

    /**
     * @param CmsMenuItem[] $items
     */
    private function resolveFallbackStartpageId(array $items): ?int
    {
        foreach ($items as $item) {
            if ($item->isStartpage()) {
                return null;
            }
        }

        if ($items === []) {
            return null;
        }

        $candidate = null;
        foreach ($items as $item) {
            if ($item->getParentId() !== null) {
                continue;
            }
            if ($candidate === null) {
                $candidate = $item;
                continue;
            }
            if ($item->getPosition() < $candidate->getPosition()) {
                $candidate = $item;
                continue;
            }
            if ($item->getPosition() === $candidate->getPosition() && $item->getId() < $candidate->getId()) {
                $candidate = $item;
            }
        }

        if ($candidate === null) {
            foreach ($items as $item) {
                if ($candidate === null) {
                    $candidate = $item;
                    continue;
                }
                if ($item->getPosition() < $candidate->getPosition()) {
                    $candidate = $item;
                    continue;
                }
                if ($item->getPosition() === $candidate->getPosition() && $item->getId() < $candidate->getId()) {
                    $candidate = $item;
                }
            }
        }

        return $candidate->getId();
    }

    private function fetchStartpageMenuItem(
        string $namespace,
        string $locale,
        bool $requireStartpage
    ): ?CmsMenuItem {
        $sql = 'SELECT * FROM marketing_menu_items WHERE namespace = ? AND locale = ?'
            . ' AND is_active = TRUE AND is_external = FALSE';

        if ($requireStartpage) {
            $sql .= ' AND is_startpage = TRUE';
        }

        $sql .= ' ORDER BY CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END, position ASC, id ASC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$namespace, $locale]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrateItem($row);
    }

    private function ensureMenuItemsImported(Page $page): void
    {
        $menuContext = $this->resolveMenuContext($page, null);
        if ($menuContext === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM marketing_menu_items WHERE menu_id = ? AND namespace = ? LIMIT 1'
        );
        $stmt->execute([$menuContext['menuId'], $menuContext['namespace']]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $definition = LegacyMarketingMenuDefinition::getDefinitionForSlug($page->getSlug())
            ?? LegacyMarketingMenuDefinition::getDefaultDefinition();

        $this->importMenuDefinition($page, $definition, $menuContext['menuId'], $menuContext['namespace']);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function importMenuDefinition(
        Page $page,
        array $definition,
        int $menuId,
        string $namespace
    ): void {
        $locales = $definition['locales'] ?? ['de', 'en'];
        if (!is_array($locales) || $locales === []) {
            $locales = ['de', 'en'];
        }

        $items = $definition['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return;
        }

        foreach ($locales as $locale) {
            $translator = new TranslationService((string) $locale);
            $this->importMenuItemsRecursive(
                $menuId,
                $namespace,
                $items,
                $translator,
                null,
                0,
                (string) $locale
            );
        }
    }

    /**
     * @param array<int, mixed> $items
     */
    private function importMenuItemsRecursive(
        int $menuId,
        string $namespace,
        array $items,
        TranslationService $translator,
        ?int $parentId,
        int $positionStart,
        string $locale
    ): int {
        $position = $positionStart;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = $this->resolveDefinitionValue($item, 'label', 'label_key', $translator);
            $href = isset($item['href']) ? (string) $item['href'] : '';
            $layout = isset($item['layout']) ? (string) $item['layout'] : self::DEFAULT_LAYOUT;
            $icon = isset($item['icon']) ? (string) $item['icon'] : null;
            $detailTitle = $this->resolveDefinitionValue($item, 'detail_title', 'detail_title_key', $translator);
            $detailText = $this->resolveDefinitionValue($item, 'detail_text', 'detail_text_key', $translator);
            $detailSubline = $this->resolveDefinitionValue($item, 'detail_subline', 'detail_subline_key', $translator);

            if ($label === '' || $href === '') {
                continue;
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO marketing_menu_items (menu_id, namespace, parent_id, label, href, icon, layout, '
                . 'detail_title, detail_text, detail_subline, position, is_external, locale, is_active, '
                . 'is_startpage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $menuId,
                $namespace,
                $parentId,
                $label,
                $href,
                $icon,
                $this->normalizeLayout($layout),
                $this->normalizeDetail($detailTitle),
                $this->normalizeDetail($detailText),
                $this->normalizeDetail($detailSubline),
                $position,
                0,
                $this->normalizeLocale($locale),
                1,
                0,
            ]);
            $insertedId = (int) $this->pdo->lastInsertId();
            $position++;

            if (isset($item['children']) && is_array($item['children']) && $item['children'] !== []) {
                $this->importMenuItemsRecursive(
                    $menuId,
                    $namespace,
                    $item['children'],
                    $translator,
                    $insertedId,
                    0,
                    $locale
                );
            }
        }

        return $position;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveDefinitionValue(
        array $item,
        string $valueKey,
        string $translationKey,
        TranslationService $translator
    ): string {
        if (isset($item[$translationKey]) && is_string($item[$translationKey])) {
            return $translator->translate($item[$translationKey]);
        }
        if (isset($item[$valueKey]) && is_string($item[$valueKey])) {
            return $item[$valueKey];
        }

        return '';
    }
}
