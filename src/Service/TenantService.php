<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Simple in-memory service for managing tenants.
 */
class TenantService
{
    /**
     * @var array<int,array<string,mixed>>
     */
    private array $tenants = [];

    /**
     * Create a new tenant record.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): void
    {
        $this->tenants[] = $data;
    }

    /**
     * Delete a tenant by UID if present.
     */
    public function delete(string $uid): void
    {
        foreach ($this->tenants as $i => $row) {
            if (($row['uid'] ?? null) === $uid) {
                unset($this->tenants[$i]);
            }
        }
    }
}
