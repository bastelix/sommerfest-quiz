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
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @return PageModule[]
     */
    public function getModulesForPage(int $pageId): array {
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
