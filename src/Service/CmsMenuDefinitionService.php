<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\CmsMenu;
use App\Domain\CmsMenuAssignment;
use App\Domain\CmsMenuItem;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

final class CmsMenuDefinitionService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @return CmsMenu[]
     */
    public function listMenus(string $namespace, bool $onlyActive = false): array
    {
        $normalizedNamespace = trim($namespace);
        if ($normalizedNamespace === '') {
            return [];
        }

        $params = [$normalizedNamespace];
        $sql = 'SELECT * FROM marketing_menus WHERE namespace = ?';

        if ($onlyActive) {
            $sql .= ' AND is_active = TRUE';
        }

        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $menus = [];
        foreach ($rows as $row) {
            $menus[] = $this->hydrateMenu($row);
        }

        return $menus;
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
     * Create a menu definition for a namespace.
     */
    public function createMenu(string $namespace, string $label, ?string $locale, bool $isActive): CmsMenu
    {
        $normalizedNamespace = trim($namespace);
        $normalizedLabel = trim($label);
        if ($normalizedNamespace === '' || $normalizedLabel === '') {
            throw new RuntimeException('Menu label is required.');
        }

        $normalizedLocale = $this->normalizeLocale($locale) ?? 'de';

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO marketing_menus (namespace, label, locale, is_active) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $normalizedNamespace,
                $normalizedLabel,
                $normalizedLocale,
                $isActive ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Creating menu failed.', 0, $exception);
        }

        $menuId = (int) $this->pdo->lastInsertId();
        $menu = $this->getMenuById($normalizedNamespace, $menuId);
        if ($menu === null) {
            throw new RuntimeException('Menu could not be loaded after creation.');
        }

        return $menu;
    }

    /**
     * Update a menu definition for a namespace.
     */
    public function updateMenu(string $namespace, int $menuId, string $label, ?string $locale, bool $isActive): CmsMenu
    {
        $normalizedNamespace = trim($namespace);
        $normalizedLabel = trim($label);
        if ($normalizedNamespace === '' || $menuId <= 0 || $normalizedLabel === '') {
            throw new RuntimeException('Menu label is required.');
        }

        $normalizedLocale = $this->normalizeLocale($locale) ?? 'de';

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE marketing_menus SET label = ?, locale = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP'
                . ' WHERE id = ? AND namespace = ?'
            );
            $stmt->execute([
                $normalizedLabel,
                $normalizedLocale,
                $isActive ? 1 : 0,
                $menuId,
                $normalizedNamespace,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Updating menu failed.', 0, $exception);
        }

        $menu = $this->getMenuById($normalizedNamespace, $menuId);
        if ($menu === null) {
            throw new RuntimeException('Menu not found after update.');
        }

        return $menu;
    }

    /**
     * Delete a menu definition for a namespace.
     */
    public function deleteMenu(string $namespace, int $menuId): bool
    {
        $normalizedNamespace = trim($namespace);
        if ($normalizedNamespace === '' || $menuId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM marketing_menus WHERE id = ? AND namespace = ?');
        $stmt->execute([$menuId, $normalizedNamespace]);

        return $stmt->rowCount() > 0;
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
    public function listAssignments(
        string $namespace,
        ?int $menuId = null,
        ?int $pageId = null,
        ?string $slot = null,
        ?string $locale = null,
        bool $onlyActive = false
    ): array {
        $normalizedNamespace = trim($namespace);
        if ($normalizedNamespace === '') {
            return [];
        }

        $params = [$normalizedNamespace];
        $sql = 'SELECT * FROM marketing_menu_assignments WHERE namespace = ?';

        if ($menuId !== null) {
            $sql .= ' AND menu_id = ?';
            $params[] = $menuId;
        }

        if ($pageId !== null) {
            $sql .= ' AND page_id = ?';
            $params[] = $pageId;
        }

        if ($slot !== null && $slot !== '') {
            $sql .= ' AND slot = ?';
            $params[] = $slot;
        }

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

    /**
     * Fetch a menu assignment by id.
     */
    public function getAssignmentById(string $namespace, int $assignmentId): ?CmsMenuAssignment
    {
        $normalizedNamespace = trim($namespace);
        if ($assignmentId <= 0 || $normalizedNamespace === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM marketing_menu_assignments WHERE id = ? AND namespace = ?'
        );
        $stmt->execute([$assignmentId, $normalizedNamespace]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateAssignment($row);
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

    /**
     * Create a menu assignment for a namespace.
     */
    public function createAssignment(
        string $namespace,
        int $menuId,
        ?int $pageId,
        string $slot,
        ?string $locale,
        bool $isActive
    ): CmsMenuAssignment {
        $normalizedNamespace = trim($namespace);
        $normalizedSlot = trim($slot);

        if (
            $normalizedNamespace === ''
            || $menuId <= 0
            || ($pageId !== null && $pageId <= 0)
            || $normalizedSlot === ''
        ) {
            throw new RuntimeException('Assignment payload is invalid.');
        }

        $normalizedLocale = $this->normalizeLocale($locale) ?? 'de';

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO marketing_menu_assignments (menu_id, page_id, namespace, slot, locale, is_active) '
                . 'VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $menuId,
                $pageId,
                $normalizedNamespace,
                $normalizedSlot,
                $normalizedLocale,
                $isActive ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Creating menu assignment failed.', 0, $exception);
        }

        $assignmentId = (int) $this->pdo->lastInsertId();
        $assignment = $this->getAssignmentById($normalizedNamespace, $assignmentId);
        if ($assignment === null) {
            throw new RuntimeException('Menu assignment could not be loaded after creation.');
        }

        return $assignment;
    }

    /**
     * Update a menu assignment for a namespace.
     */
    public function updateAssignment(
        string $namespace,
        int $assignmentId,
        int $menuId,
        ?int $pageId,
        string $slot,
        ?string $locale,
        bool $isActive
    ): CmsMenuAssignment {
        $normalizedNamespace = trim($namespace);
        $normalizedSlot = trim($slot);

        if (
            $normalizedNamespace === ''
            || $assignmentId <= 0
            || $menuId <= 0
            || ($pageId !== null && $pageId <= 0)
            || $normalizedSlot === ''
        ) {
            throw new RuntimeException('Assignment payload is invalid.');
        }

        $normalizedLocale = $this->normalizeLocale($locale) ?? 'de';

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE marketing_menu_assignments SET menu_id = ?, page_id = ?, slot = ?, locale = ?, '
                . 'is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND namespace = ?'
            );
            $stmt->execute([
                $menuId,
                $pageId,
                $normalizedSlot,
                $normalizedLocale,
                $isActive ? 1 : 0,
                $assignmentId,
                $normalizedNamespace,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Updating menu assignment failed.', 0, $exception);
        }

        $assignment = $this->getAssignmentById($normalizedNamespace, $assignmentId);
        if ($assignment === null) {
            throw new RuntimeException('Menu assignment not found after update.');
        }

        return $assignment;
    }

    /**
     * Delete a menu assignment for a namespace.
     */
    public function deleteAssignment(string $namespace, int $assignmentId): bool
    {
        $normalizedNamespace = trim($namespace);
        if ($normalizedNamespace === '' || $assignmentId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM marketing_menu_assignments WHERE id = ? AND namespace = ?');
        $stmt->execute([$assignmentId, $normalizedNamespace]);

        return $stmt->rowCount() > 0;
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
     * Check whether any assignment exists for the given slot, regardless of active status.
     */
    public function hasAssignmentForSlot(
        string $namespace,
        string $slot,
        ?string $locale = null,
        ?int $pageId = null
    ): bool {
        $normalizedNamespace = trim($namespace);
        $normalizedSlot = trim($slot);

        if ($normalizedNamespace === '' || $normalizedSlot === '') {
            return false;
        }

        $params = [$normalizedNamespace, $normalizedSlot];
        $sql = 'SELECT 1 FROM marketing_menu_assignments WHERE namespace = ? AND slot = ?';

        if ($pageId !== null) {
            if ($pageId <= 0) {
                return false;
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

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Check whether any assignment exists for the given slot in a namespace.
     */
    public function hasAssignmentsForSlot(string $namespace, string $slot): bool
    {
        $normalizedNamespace = trim($namespace);
        $normalizedSlot = trim($slot);

        if ($normalizedNamespace === '' || $normalizedSlot === '') {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM marketing_menu_assignments WHERE namespace = ? AND slot = ? LIMIT 1'
        );
        $stmt->execute([$normalizedNamespace, $normalizedSlot]);

        return $stmt->fetchColumn() !== false;
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
