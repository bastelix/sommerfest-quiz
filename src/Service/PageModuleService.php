<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\PageModule;
use App\Infrastructure\Database;
use PDO;

/**
 * Loads page modules for marketing pages.
 */
class PageModuleService
{
    /** @var list<string> */
    public const ALLOWED_TYPES = ['latest-news'];

    /** @var list<string> */
    public const ALLOWED_POSITIONS = ['before-content', 'after-content'];

    private PDO $pdo;
    private PageService $pages;

    public function __construct(?PDO $pdo = null, ?PageService $pages = null) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->pages = $pages ?? new PageService($this->pdo);
    }

    /**
     * @return PageModule[]
     */
    public function getModulesForPage(int $pageId): array {
        $modules = $this->fetchModulesForPageId($pageId);
        if ($modules !== []) {
            return $modules;
        }

        $fallbackPageId = $this->resolveFallbackPageId($pageId);
        if ($fallbackPageId === null) {
            return [];
        }

        return $this->fetchModulesForPageId($fallbackPageId);
    }

    /**
     * @return PageModule[]
     */
    private function fetchModulesForPageId(int $pageId): array {
        $stmt = $this->pdo->prepare(
            'SELECT id, page_id, type, config, position FROM page_modules WHERE page_id = ? ORDER BY position, id'
        );
        $stmt->execute([$pageId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $modules = [];

        foreach ($rows as $row) {
            $module = $this->mapRow($row);
            if ($module !== null) {
                $modules[] = $module;
            }
        }

        return $modules;
    }

    /**
     * @return array<string, PageModule[]>
     */
    public function getModulesByPosition(int $pageId): array {
        $grouped = [];

        foreach ($this->getModulesForPage($pageId) as $module) {
            $grouped[$module->getPosition()][] = $module;
        }

        return $grouped;
    }

    private function mapRow(array $row): ?PageModule
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $pageId = isset($row['page_id']) ? (int) $row['page_id'] : 0;
        $type = isset($row['type']) ? trim((string) $row['type']) : '';
        $position = isset($row['position']) ? trim((string) $row['position']) : '';
        $configRaw = isset($row['config']) ? (string) $row['config'] : '';

        if ($id <= 0 || $pageId <= 0 || $type === '' || $position === '') {
            return null;
        }

        $config = $this->decodeConfig($configRaw);

        return new PageModule($id, $pageId, $type, $config, $position);
    }

    private function resolveFallbackPageId(int $pageId): ?int {
        $page = $this->pages->findById($pageId);
        if ($page === null) {
            return null;
        }

        if ($page->getNamespace() === PageService::DEFAULT_NAMESPACE) {
            return null;
        }

        $fallbackPage = $this->pages->findByKey(PageService::DEFAULT_NAMESPACE, $page->getSlug());
        if ($fallbackPage === null) {
            return null;
        }

        return $fallbackPage->getId();
    }

    /**
     * Find a single module by identifier.
     */
    public function findById(int $id): ?PageModule
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, page_id, type, config, position FROM page_modules WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    /**
     * Create a new page module.
     *
     * @param array<string, mixed> $config
     */
    public function create(int $pageId, string $type, array $config, string $position): PageModule
    {
        $type = trim($type);
        $position = trim($position);

        if ($pageId <= 0) {
            throw new \InvalidArgumentException('A valid page must be selected.');
        }
        if ($type === '') {
            throw new \InvalidArgumentException('Module type is required.');
        }
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Unknown module type: ' . $type);
        }
        if ($position === '' || !in_array($position, self::ALLOWED_POSITIONS, true)) {
            throw new \InvalidArgumentException('Position must be one of: ' . implode(', ', self::ALLOWED_POSITIONS));
        }

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare(
            'INSERT INTO page_modules (page_id, type, config, position) VALUES (:pageId, :type, :config, :position)'
        );
        $stmt->bindValue('pageId', $pageId, PDO::PARAM_INT);
        $stmt->bindValue('type', $type);
        $stmt->bindValue('config', $configJson);
        $stmt->bindValue('position', $position);
        $stmt->execute();

        $id = (int) $this->pdo->lastInsertId();
        $module = $this->findById($id);

        if ($module === null) {
            throw new \RuntimeException('Failed to persist page module.');
        }

        return $module;
    }

    /**
     * Update an existing page module.
     *
     * @param array<string, mixed> $config
     */
    public function update(int $id, string $type, array $config, string $position): PageModule
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            throw new \InvalidArgumentException('Page module not found.');
        }

        $type = trim($type);
        $position = trim($position);

        if ($type === '') {
            throw new \InvalidArgumentException('Module type is required.');
        }
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Unknown module type: ' . $type);
        }
        if ($position === '' || !in_array($position, self::ALLOWED_POSITIONS, true)) {
            throw new \InvalidArgumentException('Position must be one of: ' . implode(', ', self::ALLOWED_POSITIONS));
        }

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare(
            'UPDATE page_modules SET type = :type, config = :config, position = :position WHERE id = :id'
        );
        $stmt->bindValue('type', $type);
        $stmt->bindValue('config', $configJson);
        $stmt->bindValue('position', $position);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $updated = $this->findById($id);
        if ($updated === null) {
            throw new \RuntimeException('Failed to update page module.');
        }

        return $updated;
    }

    /**
     * Delete a page module by identifier.
     */
    public function delete(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM page_modules WHERE id = :id');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig(string $config): array
    {
        $trimmed = trim($config);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
