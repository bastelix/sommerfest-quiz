<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\CmsMenu;
use App\Domain\CmsMenuAssignment;
use App\Domain\CmsMenuItem;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;

final class CmsMenuDefinitionService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    public function getMenuById(string $namespace, int $menuId): ?CmsMenu
    {
        $normalizedNamespace = trim($namespace);
        if ($menuId <= 0 || $normalizedNamespace === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM marketing_menus WHERE id = ? AND namespace = ?'
        );
        $stmt->execute([$menuId, $normalizedNamespace]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateMenu($row);
    }

    /**
     * @return CmsMenuItem[]
     */
    public function getMenuItemsForMenu(
        string $namespace,
        int $menuId,
        ?string $locale = null,
        bool $onlyActive = true
    ): array {
        $normalizedNamespace = trim($namespace);
        if ($menuId <= 0 || $normalizedNamespace === '') {
            return [];
        }

        $params = [$menuId, $normalizedNamespace];
        $sql = 'SELECT * FROM marketing_menu_items WHERE menu_id = ? AND namespace = ?';

        $normalizedLocale = $this->normalizeLocale($locale);
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
            $items[] = $this->hydrateMenuItem($row);
        }

        return $items;
    }

    /**
     * @return CmsMenuAssignment[]
     */
    public function getAssignmentsForPage(
        string $namespace,
        int $pageId,
        ?string $locale = null,
        bool $onlyActive = true
    ): array {
        $normalizedNamespace = trim($namespace);
        if ($pageId <= 0 || $normalizedNamespace === '') {
            return [];
        }

        $params = [$pageId, $normalizedNamespace];
        $sql = 'SELECT * FROM marketing_menu_assignments WHERE page_id = ? AND namespace = ?';

        $normalizedLocale = $this->normalizeLocale($locale);
        if ($normalizedLocale !== null) {
            $sql .= ' AND locale = ?';
            $params[] = $normalizedLocale;
        }

        if ($onlyActive) {
            $sql .= ' AND is_active = TRUE';
        }

        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $assignments = [];

        foreach ($rows as $row) {
            $assignments[] = $this->hydrateAssignment($row);
        }

        return $assignments;
    }

    public function getAssignmentForSlot(
        string $namespace,
        string $slot,
        ?string $locale = null,
        ?int $pageId = null,
        bool $onlyActive = true
    ): ?CmsMenuAssignment {
        $normalizedNamespace = trim($namespace);
        $normalizedSlot = trim($slot);

        if ($normalizedNamespace === '' || $normalizedSlot === '') {
            return null;
        }

        $params = [$normalizedNamespace, $normalizedSlot];
        $sql = 'SELECT * FROM marketing_menu_assignments WHERE namespace = ? AND slot = ?';

        if ($pageId !== null) {
            if ($pageId <= 0) {
                return null;
            }
            $sql .= ' AND page_id = ?';
            $params[] = $pageId;
        } else {
            $sql .= ' AND page_id IS NULL';
        }

        $normalizedLocale = $this->normalizeLocale($locale);
        if ($normalizedLocale !== null) {
            $sql .= ' AND locale = ?';
            $params[] = $normalizedLocale;
        }

        if ($onlyActive) {
            $sql .= ' AND is_active = TRUE';
        }

        $sql .= ' ORDER BY id ASC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateAssignment($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateMenu(array $row): CmsMenu
    {
        $updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable((string) $row['updated_at'])
            : null;

        return new CmsMenu(
            (int) $row['id'],
            (string) $row['namespace'],
            (string) $row['label'],
            (string) ($row['locale'] ?? 'de'),
            (bool) ($row['is_active'] ?? true),
            $updatedAt
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateMenuItem(array $row): CmsMenuItem
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
            (string) ($row['layout'] ?? 'link'),
            isset($row['detail_title']) ? (string) $row['detail_title'] : null,
            isset($row['detail_text']) ? (string) $row['detail_text'] : null,
            isset($row['detail_subline']) ? (string) $row['detail_subline'] : null,
            (bool) ($row['is_startpage'] ?? false),
            $updatedAt
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateAssignment(array $row): CmsMenuAssignment
    {
        $updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable((string) $row['updated_at'])
            : null;

        return new CmsMenuAssignment(
            (int) $row['id'],
            (int) $row['menu_id'],
            isset($row['page_id']) ? (int) $row['page_id'] : null,
            (string) $row['namespace'],
            (string) $row['slot'],
            (string) ($row['locale'] ?? 'de'),
            (bool) ($row['is_active'] ?? true),
            $updatedAt
        );
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $normalized = strtolower(trim($locale));
        return $normalized !== '' ? $normalized : null;
    }
}
