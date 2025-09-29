<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Simple audit logger writing events to the database.
 */
class AuditLogger
{
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Log an action with optional context data.
     *
     * @param string               $action  Name of the action.
     * @param array<string,mixed>  $context Additional context information.
     */
    public function log(string $action, array $context = []): void {
        $json = json_encode($context);
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'pgsql'
            ? 'INSERT INTO audit_logs(action, context) VALUES(?, ?::jsonb)'
            : 'INSERT INTO audit_logs(action, context) VALUES(?, ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$action, $json]);
    }
}
