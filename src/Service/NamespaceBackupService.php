<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Exports and imports all namespace-scoped data for complete backup & restore.
 *
 * Covers all 21+ namespace-scoped database tables plus the optional
 * design-token JSON file from content/design/<namespace>.json.
 */
final class NamespaceBackupService
{
    private const VERSION = '1.0';

    /**
     * Tables exported/imported in dependency order.
     * Key = JSON key in backup, Value = DB table name.
     */
    private const TABLES = [
        // Namespace metadata (no FK dependencies)
        'namespace_profile' => 'namespace_profile',
        'project_settings' => 'project_settings',

        // Events (base table for quiz content)
        'events' => 'events',

        // Event-dependent tables
        'config' => 'config',
        'catalogs' => 'catalogs',
        'teams' => 'teams',
        'players' => 'players',
        'results' => 'results',
        'question_results' => 'question_results',
        'photo_consents' => 'photo_consents',
        'summary_photos' => 'summary_photos',
        'active_event' => 'active_event',
        'team_names' => 'team_names',
        'team_name_ai_cache' => 'team_name_ai_cache',

        // CMS
        'pages' => 'pages',
        'page_ai_jobs' => 'page_ai_jobs',

        // Marketing
        'marketing_menus' => 'marketing_menus',
        'marketing_menu_items' => 'marketing_menu_items',
        'marketing_menu_assignments' => 'marketing_menu_assignments',
        'marketing_footer_blocks' => 'marketing_footer_blocks',
        'newsletter_campaigns' => 'newsletter_campaigns',

        // Access & auth
        'user_namespaces' => 'user_namespaces',
        'namespace_api_tokens' => 'namespace_api_tokens',

        // Mail
        'mail_providers' => 'mail_providers',
    ];

    /**
     * Delete order: children before parents to respect FK constraints.
     */
    private const DELETE_ORDER = [
        'marketing_menu_items',
        'marketing_menu_assignments',
        'marketing_menus',
        'marketing_footer_blocks',
        'newsletter_campaigns',
        'page_ai_jobs',
        'pages',
        'question_results',
        'results',
        'photo_consents',
        'summary_photos',
        'players',
        'teams',
        'active_event',
        'team_names',
        'team_name_ai_cache',
        'catalogs',
        'config',
        'events',
        'namespace_api_tokens',
        'user_namespaces',
        'mail_providers',
        'project_settings',
        'namespace_profile',
    ];

    /**
     * Tables with IDENTITY or SERIAL primary keys that need OVERRIDING SYSTEM VALUE.
     */
    private const IDENTITY_TABLES = [
        'config',
        'results',
        'question_results',
        'photo_consents',
        'summary_photos',
        'pages',
        'page_ai_jobs',
        'marketing_menus',
        'marketing_menu_items',
        'marketing_menu_assignments',
        'marketing_footer_blocks',
        'newsletter_campaigns',
        'namespace_api_tokens',
        'mail_providers',
        'team_names',
        'team_name_ai_cache',
    ];

    private string $contentDesignDir;

    public function __construct(
        private readonly PDO $pdo,
        ?string $contentDesignDir = null,
    ) {
        $this->contentDesignDir = $contentDesignDir ?? dirname(__DIR__, 2) . '/content/design';
    }

    /**
     * Export all data belonging to a namespace.
     *
     * @return array<string, mixed> Complete backup structure
     */
    public function export(string $namespace): array
    {
        $data = [
            'meta' => [
                'namespace' => $namespace,
                'exported_at' => date('c'),
                'version' => self::VERSION,
            ],
        ];

        foreach (self::TABLES as $key => $table) {
            $data[$key] = $this->exportTable($table, $namespace);
        }

        // Design file
        $designFile = $this->contentDesignDir . '/' . $namespace . '.json';
        if (is_readable($designFile)) {
            $content = file_get_contents($designFile);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                $data['design_file'] = is_array($decoded) ? $decoded : null;
            }
        } else {
            $data['design_file'] = null;
        }

        return $data;
    }

    /**
     * Import (restore) all data for a namespace from a backup.
     *
     * Performs a clean restore: deletes all existing namespace data first,
     * then inserts from the backup. Wrapped in a transaction.
     *
     * @param array<string, mixed> $data Backup data as returned by export()
     */
    public function import(string $namespace, array $data): void
    {
        $this->pdo->beginTransaction();

        try {
            // 1. Delete all existing data (children first)
            foreach (self::DELETE_ORDER as $table) {
                $this->deleteNamespaceData($table, $namespace);
            }

            // 2. Insert in dependency order (parents first)
            foreach (self::TABLES as $key => $table) {
                if (!isset($data[$key]) || !is_array($data[$key])) {
                    continue;
                }

                $rows = $data[$key];

                // Single-row tables (namespace_profile, project_settings) might be stored as object
                if ($key === 'namespace_profile' || $key === 'project_settings') {
                    if (!empty($rows) && !isset($rows[0])) {
                        $rows = [$rows];
                    }
                }

                if ($key === 'pages') {
                    $rows = $this->sortPagesByParent($rows);
                }

                $useOverride = in_array($table, self::IDENTITY_TABLES, true);

                foreach ($rows as $row) {
                    if (!is_array($row) || empty($row)) {
                        continue;
                    }
                    // Ensure namespace column is set to target namespace
                    $row['namespace'] = $namespace;
                    $this->insertRow($table, $row, $useOverride);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        // 3. Design file (outside transaction, filesystem)
        if (isset($data['design_file']) && is_array($data['design_file'])) {
            $designFile = $this->contentDesignDir . '/' . $namespace . '.json';
            $dir = dirname($designFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents(
                $designFile,
                json_encode(
                    $data['design_file'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . "\n"
            );
        }
    }

    /**
     * Get a summary of what a backup contains (entity counts).
     *
     * @param array<string, mixed> $data Backup data
     * @return array<string, int>
     */
    public function summarize(array $data): array
    {
        $summary = [];
        foreach (self::TABLES as $key => $table) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                $summary[$key] = 0;
                continue;
            }
            $rows = $data[$key];
            // Single-row tables stored as object
            if (($key === 'namespace_profile' || $key === 'project_settings') && !empty($rows) && !isset($rows[0])) {
                $summary[$key] = 1;
            } else {
                $summary[$key] = count($rows);
            }
        }
        $summary['design_file'] = isset($data['design_file']) && is_array($data['design_file']) ? 1 : 0;
        return $summary;
    }

    // ── Private Helpers ──────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function exportTable(string $table, string $namespace): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$table} WHERE namespace = ?"
        );
        $stmt->execute([$namespace]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function deleteNamespaceData(string $table, string $namespace): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$table} WHERE namespace = ?"
        );
        $stmt->execute([$namespace]);
    }

    /**
     * Insert a single row, optionally with OVERRIDING SYSTEM VALUE for identity columns.
     *
     * @param array<string, mixed> $row
     */
    private function insertRow(string $table, array $row, bool $useOverride): void
    {
        $columns = array_keys($row);
        $placeholders = array_fill(0, count($columns), '?');

        $columnList = implode(', ', array_map(fn(string $col) => '"' . $col . '"', $columns));
        $placeholderList = implode(', ', $placeholders);

        $override = $useOverride ? ' OVERRIDING SYSTEM VALUE' : '';
        $sql = "INSERT INTO {$table} ({$columnList}){$override} VALUES ({$placeholderList})";

        $stmt = $this->pdo->prepare($sql);
        $values = array_values($row);

        // Convert arrays/objects to JSON for JSONB columns
        foreach ($values as $i => $value) {
            if (is_array($value)) {
                $values[$i] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $values[$i] = $value ? 't' : 'f';
            }
        }

        $stmt->execute($values);
    }

    /**
     * Sort pages so that parent pages are inserted before their children.
     *
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    private function sortPagesByParent(array $pages): array
    {
        // Build index by id
        $byId = [];
        foreach ($pages as $page) {
            $id = $page['id'] ?? null;
            if ($id !== null) {
                $byId[$id] = $page;
            }
        }

        $sorted = [];
        $added = [];

        $addPage = null;
        $addPage = function (array $page) use (&$sorted, &$added, &$addPage, $byId): void {
            $id = $page['id'] ?? null;
            if ($id !== null && isset($added[$id])) {
                return;
            }

            // Add parent first if it exists
            $parentId = $page['parent_id'] ?? null;
            if ($parentId !== null && isset($byId[$parentId]) && !isset($added[$parentId])) {
                $addPage($byId[$parentId]);
            }

            $sorted[] = $page;
            if ($id !== null) {
                $added[$id] = true;
            }
        };

        foreach ($pages as $page) {
            $addPage($page);
        }

        return $sorted;
    }
}
