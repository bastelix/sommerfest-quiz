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

        return $this->fetchItemsForPageId($pageId, $page->getNamespace(), $locale, $onlyActive);
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
        ?int $position = null,
        bool $isExternal = false,
        ?string $locale = null,
        bool $isActive = true
    ): MarketingPageMenuItem {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            throw new RuntimeException('Page not found.');
        }

        $normalizedLabel = $this->normalizeLabel($label);
        $normalizedHref = $this->normalizeHref($href);
        $normalizedIcon = $this->normalizeIcon($icon);
        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedPosition = $position
            ?? $this->determineNextPosition($pageId, $page->getNamespace(), $normalizedLocale);

        $stmt = $this->pdo->prepare(
            'INSERT INTO marketing_page_menu_items (page_id, namespace, label, href, icon, position, '
            . 'is_external, locale, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        try {
            $stmt->execute([
                $pageId,
                $page->getNamespace(),
                $normalizedLabel,
                $normalizedHref,
                $normalizedIcon,
                $normalizedPosition,
                $isExternal ? 1 : 0,
                $normalizedLocale,
                $isActive ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
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
        ?int $position = null,
        bool $isExternal = false,
        ?string $locale = null,
        bool $isActive = true
    ): MarketingPageMenuItem {
        $existing = $this->getMenuItemById($id);
        if ($existing === null) {
            throw new RuntimeException('Menu item not found.');
        }

        $normalizedLabel = $this->normalizeLabel($label);
        $normalizedHref = $this->normalizeHref($href);
        $normalizedIcon = $this->normalizeIcon($icon);
        $normalizedLocale = $this->normalizeLocale($locale ?? $existing->getLocale());
        $normalizedPosition = $position ?? $existing->getPosition();

        $stmt = $this->pdo->prepare(
            'UPDATE marketing_page_menu_items SET label = ?, href = ?, icon = ?, position = ?, is_external = ?, '
            . 'locale = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );

        try {
            $stmt->execute([
                $normalizedLabel,
                $normalizedHref,
                $normalizedIcon,
                $normalizedPosition,
                $isExternal ? 1 : 0,
                $normalizedLocale,
                $isActive ? 1 : 0,
                $id,
            ]);
        } catch (PDOException $exception) {
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
            (int) ($row['position'] ?? 0),
            (bool) ($row['is_external'] ?? false),
            (string) ($row['locale'] ?? 'de'),
            (bool) ($row['is_active'] ?? true),
            $updatedAt
        );
    }

    private function determineNextPosition(int $pageId, string $namespace, string $locale): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT MAX(position) FROM marketing_page_menu_items WHERE page_id = ? AND namespace = ? AND locale = ?'
        );
        $stmt->execute([$pageId, $namespace, $locale]);
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
}
