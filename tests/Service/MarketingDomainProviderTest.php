<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\DomainService;
use App\Service\MarketingDomainProvider;
use PDO;
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
