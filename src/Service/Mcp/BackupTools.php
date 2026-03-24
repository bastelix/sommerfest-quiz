<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Service\NamespaceBackupService;
use PDO;

final class BackupTools
{
    private NamespaceBackupService $backupService;

    private const NS_PROP = ['type' => 'string', 'description' => 'Optional namespace (defaults to the token namespace)'];

    public function __construct(private readonly string $defaultNamespace, PDO $pdo)
    {
        $this->backupService = new NamespaceBackupService($pdo);
    }

    private function resolveNamespace(array $args): string
    {
        $ns = isset($args['namespace']) && is_string($args['namespace']) ? trim($args['namespace']) : '';
        return $ns !== '' ? $ns : $this->defaultNamespace;
    }

    /**
     * @return list<array{name: string, method: string, description: string, inputSchema: array}>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'export_namespace',
                'method' => 'exportNamespace',
                'description' => 'Export a complete backup of all namespace data (pages, menus, footer blocks, events, catalogs, teams, results, design tokens, settings, and more) as JSON. Returns a full snapshot that can be used with import_namespace to restore.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'import_namespace',
                'method' => 'importNamespace',
                'description' => 'Restore a namespace from a complete backup JSON. This performs a CLEAN RESTORE: all existing data in the target namespace is deleted before importing from the backup. The backup must be a JSON object as returned by export_namespace.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'backup' => ['type' => 'object', 'description' => 'The full backup JSON object as returned by export_namespace'],
                    ],
                    'required' => ['backup'],
                ],
            ],
        ];
    }

    // ── Tool Handlers ────────────────────────────────────────────────

    public function exportNamespace(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $backup = $this->backupService->export($ns);
        $summary = $this->backupService->summarize($backup);

        return [
            'status' => 'ok',
            'namespace' => $ns,
            'summary' => $summary,
            'backup' => $backup,
        ];
    }

    public function importNamespace(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $backup = $args['backup'] ?? null;
        if (!is_array($backup) || !isset($backup['meta'])) {
            throw new \InvalidArgumentException('backup must be a valid backup object with a meta key');
        }

        $this->backupService->import($ns, $backup);
        $summary = $this->backupService->summarize($backup);

        return [
            'status' => 'ok',
            'namespace' => $ns,
            'restored' => $summary,
        ];
    }
}
