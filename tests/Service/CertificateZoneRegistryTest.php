<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CertificateZoneRegistry;
use Tests\TestCase;

final class CertificateZoneRegistryTest extends TestCase
{
    public function testBackfillActiveDomainsCreatesMissingZones(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(<<<'SQL'
            CREATE TABLE domains (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                host TEXT NOT NULL,
                normalized_host TEXT NOT NULL UNIQUE,
                zone TEXT NOT NULL,
                namespace TEXT,
                label TEXT,
                is_active INTEGER NOT NULL DEFAULT 1
            );
        SQL);
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT)');

        $pdo->exec(<<<'SQL'
            INSERT INTO domains (host, normalized_host, zone, namespace, label, is_active) VALUES
                ('kaaroo.com', 'kaaroo.com', 'kaaroo.com', NULL, NULL, 1),
                ('inactive.example', 'inactive.example', 'inactive.example', NULL, NULL, 0),
                ('www.demo.com', 'demo.com', 'demo.com', NULL, NULL, 1)
        SQL);

        $this->setDatabase($pdo);

        $registry = new CertificateZoneRegistry($pdo);
        $registry->ensureZone('existing.com', 'hetzner', false);

        $registry->backfillActiveDomains('dns_cf');

        $rows = $pdo->query('SELECT zone, provider, wildcard_enabled, status FROM certificate_zones ORDER BY zone ASC');
        $result = $rows !== false ? $rows->fetchAll(\PDO::FETCH_ASSOC) : [];

        $normalized = array_map(
            static fn (array $row): array => [
                'zone' => $row['zone'],
                'provider' => $row['provider'],
                'wildcard_enabled' => (int) $row['wildcard_enabled'],
                'status' => $row['status'],
            ],
            $result
        );

        $this->assertSame([
            ['zone' => 'demo.com', 'provider' => 'dns_cf', 'wildcard_enabled' => 1, 'status' => 'pending'],
            ['zone' => 'existing.com', 'provider' => 'dns_hetzner', 'wildcard_enabled' => 0, 'status' => 'pending'],
            ['zone' => 'kaaroo.com', 'provider' => 'dns_cf', 'wildcard_enabled' => 1, 'status' => 'pending'],
        ], $normalized);
    }
}
