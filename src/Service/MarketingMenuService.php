<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\MarketingPageMenuItem;
use App\Domain\Page;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

final class MarketingMenuService
{
    private const DEFAULT_LAYOUT = 'link';
    private const ALLOWED_LAYOUTS = ['link', 'dropdown', 'mega', 'column'];

    private PDO $pdo;

    private PageService $pages;

    public function __construct(?PDO $pdo = null, ?PageService $pages = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->pages = $pages ?? new PageService($this->pdo);
    }

    /**
     * Load menu items for a page slug with namespace fallback support.
     *
     * @return MarketingPageMenuItem[]
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

        $items = $this->fetchItemsForPageId(
            $page->getId(),
            $page->getNamespace(),
            $locale,
            $onlyActive
        );

        if ($items !== [] || $page->getNamespace() === PageService::DEFAULT_NAMESPACE) {
            return $items;
        }

        $fallbackPage = $this->pages->findByKey(PageService::DEFAULT_NAMESPACE, $page->getSlug());
        if ($fallbackPage === null) {
            return $items;
        }

        $this->ensureMenuItemsImported($fallbackPage);

        return $this->fetchItemsForPageId(
            $fallbackPage->getId(),
            $fallbackPage->getNamespace(),
            $locale,
            $onlyActive
        );
    }

    /**
     * Load menu items for a page id.
     *
     * @return MarketingPageMenuItem[]
     */
    public function getMenuItemsForPage(int $pageId, ?string $locale = null, bool $onlyActive = true): array
    {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            return [];
        }

        $this->ensureMenuItemsImported($page);

        return $this->fetchItemsForPageId($pageId, $page->getNamespace(), $locale, $onlyActive);
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

        $page = $this->pages->findById($item->getPageId());
        if ($page === null) {
            return null;
        }

        return $page->getSlug();
    }

    public function resolveStartpage(
        string $namespace,
        ?string $locale = null,
        bool $requireExplicit = false
    ): ?MarketingPageMenuItem {
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

        $this->pdo->beginTransaction();

        try {
            $this->resetStartpages($page->getNamespace());

            $sql = 'UPDATE marketing_page_menu_items SET is_startpage = TRUE WHERE page_id = ? AND namespace = ?';
            $params = [$pageId, $page->getNamespace()];
            if ($normalizedLocale !== null) {
                $sql .= ' AND locale = ?';
                $params[] = $normalizedLocale;
            }

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

            throw new RuntimeException('Setting startpage failed.', 0, $exception);
        }
    }

    public function clearStartpagesForNamespace(string $namespace): void
    {
        $this->resetStartpages($namespace);
    }

    /**
     * Fetch a single menu item by its id.
     */
    public function getMenuItemById(int $id): ?MarketingPageMenuItem
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM marketing_page_menu_items WHERE id = ?');
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
    ): MarketingPageMenuItem {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            throw new RuntimeException('Page not found.');
        }

        $parent = $this->normalizeParent($pageId, $parentId);
        $normalizedLabel = $this->normalizeLabel($label);
        $normalizedHref = $this->normalizeHref($href);
        $normalizedIcon = $this->normalizeIcon($icon);
        $normalizedLayout = $this->normalizeLayout($layout);
        $normalizedDetailTitle = $this->normalizeDetail($detailTitle);
        $normalizedDetailText = $this->normalizeDetail($detailText);
        $normalizedDetailSubline = $this->normalizeDetail($detailSubline);
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedPosition = $position
            ?? $this->determineNextPosition($pageId, $page->getNamespace(), $normalizedLocale, $parent?->getId());

        $this->pdo->beginTransaction();

        try {
            if ($isStartpage) {
                $this->resetStartpages($page->getNamespace());
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO marketing_page_menu_items (page_id, namespace, parent_id, label, href, icon, layout, '
                . 'detail_title, detail_text, detail_subline, position, is_external, locale, is_active, '
                . 'is_startpage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $pageId,
                $page->getNamespace(),
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
    ): MarketingPageMenuItem {
        $existing = $this->getMenuItemById($id);
        if ($existing === null) {
            throw new RuntimeException('Menu item not found.');
        }

        $parent = $this->normalizeParent($existing->getPageId(), $parentId, $id);
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
                'UPDATE marketing_page_menu_items SET parent_id = ?, label = ?, href = ?, icon = ?, layout = ?, '
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

        $stmt = $this->pdo->prepare('DELETE FROM marketing_page_menu_items WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param int[] $orderedIds
     */
    public function reorderMenuItems(int $pageId, array $orderedIds): void
    {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            throw new RuntimeException('Page not found.');
        }

        if ($orderedIds === []) {
            return;
        }

        $orderedIds = array_values($orderedIds);
        $itemsPayload = is_int($orderedIds[0]) ? null : $orderedIds;

        if ($itemsPayload === null) {
            $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));
            $existingIds = $this->getMenuItemIdsForPage($pageId);

            if ($existingIds === []) {
                return;
            }

            $missing = array_diff($orderedIds, $existingIds);
            if ($missing !== []) {
                throw new RuntimeException('Unknown menu item id(s) for page: ' . implode(', ', $missing));
            }

            $this->pdo->beginTransaction();

            try {
                $update = $this->pdo->prepare(
                    'UPDATE marketing_page_menu_items SET position = ? WHERE page_id = ? AND id = ?'
                );
                $position = 0;

                foreach ($orderedIds as $orderedId) {
                    $update->execute([$position, $pageId, $orderedId]);
                    $position++;
                }

                foreach ($existingIds as $itemId) {
                    if (in_array($itemId, $orderedIds, true)) {
                        continue;
                    }

                    $update->execute([$position, $pageId, $itemId]);
                    $position++;
                }

                $this->pdo->commit();
            } catch (PDOException $exception) {
                $this->pdo->rollBack();
                throw new RuntimeException('Updating menu order failed: ' . $exception->getMessage(), 0, $exception);
            }

            return;
        }

        $normalizedItems = [];
        foreach ($itemsPayload as $entry) {
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

        $existingIds = $this->getMenuItemIdsForPage($pageId);
        $missing = array_diff(array_keys($normalizedItems), $existingIds);
        if ($missing !== []) {
            throw new RuntimeException('Unknown menu item id(s) for page: ' . implode(', ', $missing));
        }

        $this->pdo->beginTransaction();

        try {
            $update = $this->pdo->prepare(
                'UPDATE marketing_page_menu_items SET position = ? WHERE page_id = ? AND id = ?'
            );

            foreach ($normalizedItems as $id => $position) {
                $update->execute([$position, $pageId, $id]);
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Updating menu order failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return MarketingPageMenuItem[]
     */
    private function fetchItemsForPageId(int $pageId, string $namespace, ?string $locale, bool $onlyActive): array
    {
        $normalizedLocale = null;
        if ($locale !== null) {
            $candidate = strtolower(trim($locale));
            if ($candidate !== '') {
                $normalizedLocale = $candidate;
            }
        }
        $params = [$pageId, $namespace];
        $sql = 'SELECT * FROM marketing_page_menu_items WHERE page_id = ? AND namespace = ?';

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

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateItem(array $row): MarketingPageMenuItem
    {
        $updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable((string) $row['updated_at'])
            : null;

        return new MarketingPageMenuItem(
            (int) $row['id'],
            (int) $row['page_id'],
            (string) $row['namespace'],
            (string) $row['label'],
            (string) $row['href'],
            isset($row['icon']) ? (string) $row['icon'] : null,
            isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            isset($row['layout']) ? (string) $row['layout'] : self::DEFAULT_LAYOUT,
            isset($row['detail_title']) ? (string) $row['detail_title'] : null,
            isset($row['detail_text']) ? (string) $row['detail_text'] : null,
            isset($row['detail_subline']) ? (string) $row['detail_subline'] : null,
            (int) ($row['position'] ?? 0),
            (bool) ($row['is_external'] ?? false),
            (string) ($row['locale'] ?? 'de'),
            (bool) ($row['is_active'] ?? true),
            (bool) ($row['is_startpage'] ?? false),
            $updatedAt
        );
    }

    private function determineNextPosition(int $pageId, string $namespace, string $locale, ?int $parentId): int
    {
        $sql = 'SELECT MAX(position) FROM marketing_page_menu_items WHERE page_id = ? AND namespace = ? AND locale = ?';
        $params = [$pageId, $namespace, $locale];

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

    private function normalizeLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            throw new RuntimeException('Menu label is required.');
        }

        return $normalized;
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
    private function getMenuItemIdsForPage(int $pageId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM marketing_page_menu_items WHERE page_id = ? ORDER BY position ASC, id ASC'
        );
        $stmt->execute([$pageId]);
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

    private function normalizeParent(int $pageId, ?int $parentId, ?int $itemId = null): ?MarketingPageMenuItem
    {
        if ($parentId === null || $parentId <= 0) {
            return null;
        }

        if ($itemId !== null && $parentId === $itemId) {
            throw new RuntimeException('Menu item cannot be its own parent.');
        }

        $parent = $this->getMenuItemById($parentId);
        if ($parent === null || $parent->getPageId() !== $pageId) {
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
            'UPDATE marketing_page_menu_items SET is_startpage = FALSE WHERE namespace = ?'
        );
        $stmt->execute([$normalizedNamespace]);
    }

    /**
     * @param MarketingPageMenuItem[] $items
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
            usort($group, static function (MarketingPageMenuItem $a, MarketingPageMenuItem $b): int {
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
                    'pageId' => $item->getPageId(),
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
     * @param MarketingPageMenuItem[] $items
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

        return $candidate?->getId();
    }

    private function fetchStartpageMenuItem(
        string $namespace,
        string $locale,
        bool $requireStartpage
    ): ?MarketingPageMenuItem {
        $sql = 'SELECT * FROM marketing_page_menu_items WHERE namespace = ? AND locale = ?'
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
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM marketing_page_menu_items WHERE page_id = ? LIMIT 1'
        );
        $stmt->execute([$page->getId()]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $definition = LegacyMarketingMenuDefinition::getDefinitionForSlug($page->getSlug());
        if ($definition === null) {
            $definition = LegacyMarketingMenuDefinition::getDefaultDefinition();
        }

        if ($definition === null) {
            return;
        }

        $this->importMenuDefinition($page, $definition);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function importMenuDefinition(Page $page, array $definition): void
    {
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
                $page,
                $items,
                $translator,
                null,
                0,
                (string) $locale
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function importMenuItemsRecursive(
        Page $page,
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
                'INSERT INTO marketing_page_menu_items (page_id, namespace, parent_id, label, href, icon, layout, '
                . 'detail_title, detail_text, detail_subline, position, is_external, locale, is_active, '
                . 'is_startpage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $page->getId(),
                $page->getNamespace(),
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
                    $page,
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
