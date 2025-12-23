<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\DomainNameHelper;
use App\Service\DomainService;
use App\Service\MarketingDomainProvider;
use PHPUnit\Framework\TestCase;
use PDO;

class DomainNameHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        DomainNameHelper::setMarketingDomainProvider(null);
        parent::tearDown();
    }

    /**
     * @dataProvider provideDomains
     *
     * @param non-empty-string $expected
     */
    public function testNormalizeStripsKnownPrefixes(string $input, string $expected): void {
        self::assertSame($expected, DomainNameHelper::normalize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDomains(): iterable {
        yield 'base domain' => ['example.com', 'example.com'];
        yield 'www prefix' => ['www.example.com', 'example.com'];
        yield 'admin prefix' => ['admin.example.com', 'example.com'];
        yield 'assistant prefix' => ['assistant.example.com', 'example.com'];
        yield 'mixed case with scheme' => ['HTTPS://ASSISTANT.Example.COM/path', 'example.com'];
    }

    /**
     * @dataProvider provideLegacyDomains
     */
    public function testNormalizeKeepsPrefixesWhenAdminStrippingDisabled(string $input, string $expected): void {
        self::assertSame($expected, DomainNameHelper::normalize($input, stripAdmin: false));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLegacyDomains(): iterable {
        yield 'www removed' => ['www.example.com', 'example.com'];
        yield 'admin kept' => ['admin.example.com', 'admin.example.com'];
        yield 'assistant kept' => ['assistant.example.com', 'assistant.example.com'];
    }

    public function testCanonicalizeUsesMarketingProvider(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS domains ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'host TEXT NOT NULL, '
            . 'normalized_host TEXT NOT NULL UNIQUE, '
            . 'namespace TEXT, '
            . 'label TEXT, '
            . 'is_active BOOLEAN NOT NULL DEFAULT TRUE, '
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
        );

        $service = new DomainService($pdo);
        $service->createDomain('promo.example.com');

        $provider = new MarketingDomainProvider(static fn (): PDO => $pdo, 0);
        DomainNameHelper::setMarketingDomainProvider($provider);

        self::assertSame('promo', DomainNameHelper::canonicalizeSlug('promo.example.com'));
        self::assertSame('promo', DomainNameHelper::canonicalizeSlug('HTTPS://Promo.Example.com'));
    }
}
