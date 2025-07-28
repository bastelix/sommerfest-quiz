<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use App\Infrastructure\Migrations\Migrator;

/**
 * Service for creating and deleting tenants using separate schemas.
 */
class TenantService
{
    private PDO $pdo;
    private string $migrationsDir;
    private ?NginxService $nginxService;

    public function __construct(PDO $pdo, ?string $migrationsDir = null, ?NginxService $nginxService = null)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = $migrationsDir ?? dirname(__DIR__, 2) . '/migrations';
        $this->nginxService = $nginxService;
    }

    /**
     * Create a new tenant schema and run migrations within it.
     */
    public function createTenant(string $uid, string $schema): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            Migrator::migrate($this->pdo, $this->migrationsDir);
        } else {
            $this->pdo->exec(sprintf('CREATE SCHEMA "%s"', $schema));
            $this->pdo->exec(sprintf('SET search_path TO "%s", public', $schema));
            Migrator::migrate($this->pdo, $this->migrationsDir);
            $this->pdo->exec('SET search_path TO public');
        }
        $stmt = $this->pdo->prepare('INSERT INTO tenants(uid, subdomain) VALUES(?, ?)');
        $stmt->execute([$uid, $schema]);

        if ($this->nginxService !== null) {
            try {
                $this->nginxService->createVhost($schema);
            } catch (\RuntimeException $e) {
                error_log('Failed to reload nginx: ' . $e->getMessage());
                throw new \RuntimeException('Nginx reload failed – check Docker installation', 0, $e);
            }
        }
    }

    /**
     * Drop the tenant schema and remove its record.
     */
    public function deleteTenant(string $uid): void
    {
        $stmt = $this->pdo->prepare('SELECT subdomain FROM tenants WHERE uid = ?');
        $stmt->execute([$uid]);
        $schema = $stmt->fetchColumn();
        if ($schema === false) {
            return;
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $this->pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schema));
        }
        $del = $this->pdo->prepare('DELETE FROM tenants WHERE uid = ?');
        $del->execute([$uid]);
    }

    /**
     * Check whether a tenant with the given subdomain exists.
     */
    public function exists(string $subdomain): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE subdomain = ?');
        $stmt->execute([$subdomain]);
        return $stmt->fetchColumn() !== false;
    }
}
