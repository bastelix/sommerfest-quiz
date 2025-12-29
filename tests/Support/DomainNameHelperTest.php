<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Infrastructure\Database;
use App\Support\DomainNameHelper;
use App\Service\MarketingDomainProvider;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DomainNameHelperTest extends TestCase
{
    private string|false $marketingDomainsEnv = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketingDomainsEnv = getenv('MARKETING_DOMAINS');
    }

    protected function tearDown(): void
    {
        if ($this->marketingDomainsEnv === false) {
            putenv('MARKETING_DOMAINS');
        } else {
            putenv('MARKETING_DOMAINS=' . $this->marketingDomainsEnv);
        }

        Database::setFactory(null);
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
        yield 'www kept' => ['www.example.com', 'www.example.com'];
        yield 'admin kept' => ['admin.example.com', 'admin.example.com'];
        yield 'assistant kept' => ['assistant.example.com', 'assistant.example.com'];
    }

    public function testCanonicalizeUsesMarketingProvider(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS marketing_domains ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'host TEXT NOT NULL, '
            . 'normalized_host TEXT NOT NULL UNIQUE)'
        );

        $stmt = $pdo->prepare('INSERT INTO marketing_domains (host, normalized_host) VALUES (?, ?)');
        $stmt->execute(['promo.example.com', DomainNameHelper::normalize('promo.example.com', stripAdmin: false)]);

        $provider = new MarketingDomainProvider(static fn (): PDO => $pdo, 0);
        DomainNameHelper::setMarketingDomainProvider($provider);

        self::assertSame('promo', DomainNameHelper::canonicalizeSlug('promo.example.com'));
        self::assertSame('promo', DomainNameHelper::canonicalizeSlug('HTTPS://Promo.Example.com'));
    }

    public function testMarketingDomainsResolveFromDatabaseWithoutEnv(): void
    {
        putenv('MARKETING_DOMAINS');

        $pdo = $this->createDomainDatabase();
        $stmt = $pdo->prepare('INSERT INTO marketing_domains (host, normalized_host) VALUES (?, ?)');
        $stmt->execute(['promo.example.com', DomainNameHelper::normalize('promo.example.com', stripAdmin: false)]);

        Database::setFactory(static fn (): PDO => $pdo);

        self::assertSame('promo', DomainNameHelper::canonicalizeSlug('promo.example.com'));
    }

    public function testExistingMarketingProviderPathIsPreserved(): void
    {
        Database::setFactory(static function (): PDO {
            throw new RuntimeException('Database factory must not be used when provider is set.');
        });

        $provider = $this->createMock(MarketingDomainProvider::class);
        $provider->expects(self::once())
            ->method('getMarketingDomains')
            ->with(stripAdmin: false)
            ->willReturn(['custom.example.com']);

        DomainNameHelper::setMarketingDomainProvider($provider);

        self::assertSame('custom', DomainNameHelper::canonicalizeSlug('custom.example.com'));
    }

    public function testCanonicalizationUsesFullMarketingHosts(): void
    {
        $provider = $this->createMock(MarketingDomainProvider::class);
        $provider->expects(self::once())
            ->method('getMarketingDomains')
            ->with(stripAdmin: false)
            ->willReturn(['promo.example.com']);

        DomainNameHelper::setMarketingDomainProvider($provider);

        self::assertSame('promo', DomainNameHelper::canonicalizeSlug('promo.example.com'));
    }

    private function createDomainDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS marketing_domains ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'host TEXT NOT NULL, '
            . 'normalized_host TEXT NOT NULL UNIQUE)'
        );

        return $pdo;
    }
}
