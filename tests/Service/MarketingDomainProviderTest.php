<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\DomainService;
use App\Service\MarketingDomainProvider;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class MarketingDomainProviderTest extends TestCase
{
    public function testFetchesOnlyActiveDomainsWithBooleanFlag(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $service = new DomainService($pdo);
        $service->createDomain('active.example.com', isActive: true);
        $service->createDomain('inactive.example.com', isActive: false);

        $provider = new MarketingDomainProvider(static fn (): PDO => $pdo, 0);

        $domains = $provider->getMarketingDomains(stripAdmin: false);
        sort($domains);

        self::assertSame(['active.example.com'], $domains);
    }

    public function testKeepsPreviousCacheOnDatabaseFailure(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pdo->exec("INSERT INTO domains (host, normalized_host, zone, namespace, label, is_active)"
            . " VALUES ('active.example.com', 'active.example.com', 'calserver.com', 'default', 'Main', 1)");

        $connectionCalls = 0;
        $provider = new MarketingDomainProvider(static function () use (&$connectionCalls, $pdo): PDO {
            $connectionCalls++;

            if ($connectionCalls === 1) {
                return $pdo;
            }

            throw new PDOException('Database not reachable');
        }, 0);

        $initial = $provider->getMarketingDomains(stripAdmin: false);

        self::assertSame(['active.example.com'], $initial);

        $second = $provider->getMarketingDomains(stripAdmin: false);

        self::assertSame($initial, $second);
    }

    public function testEmptyDatabaseResultExpiresImmediately(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $provider = new MarketingDomainProvider(static fn (): PDO => $pdo);

        self::assertSame([], $provider->getMarketingDomains(stripAdmin: false));

        $service = new DomainService($pdo);
        $service->createDomain('cached.example.com', isActive: true);

        $domains = $provider->getMarketingDomains(stripAdmin: false);

        self::assertSame(['cached.example.com'], $domains);
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS domains ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'host TEXT NOT NULL, '
            . 'normalized_host TEXT NOT NULL UNIQUE, '
            . 'zone TEXT NOT NULL, '
            . 'namespace TEXT, '
            . 'label TEXT, '
            . 'is_active BOOLEAN NOT NULL DEFAULT TRUE, '
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
        );
    }
}
