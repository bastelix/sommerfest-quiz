<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CertificateProvisioningService;
use App\Service\MarketingDomainProvider;
use App\Support\DomainNameHelper;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MarketingDomainProviderTest extends TestCase
{
    private ?string $marketingEnv = null;
    private ?string $mainEnv = null;

    protected function setUp(): void
    {
        $this->marketingEnv = getenv('MARKETING_DOMAINS') === false ? null : (string) getenv('MARKETING_DOMAINS');
        $this->mainEnv = getenv('MAIN_DOMAIN') === false ? null : (string) getenv('MAIN_DOMAIN');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('MARKETING_DOMAINS', $this->marketingEnv);
        $this->restoreEnv('MAIN_DOMAIN', $this->mainEnv);
    }

    public function testFallsBackToEnvironmentWhenDatabaseIsEmpty(): void
    {
        putenv('MARKETING_DOMAINS=promo.example.com admin.example.com promo.example.com');
        $_ENV['MARKETING_DOMAINS'] = 'promo.example.com admin.example.com promo.example.com';

        $provider = $this->createProvider();

        self::assertSame(
            ['promo.example.com', 'example.com'],
            $provider->getMarketingDomains()
        );

        self::assertSame(
            ['promo.example.com', 'admin.example.com'],
            $provider->getMarketingDomains(stripAdmin: false)
        );
    }

    public function testCollectMarketingDomainsDeduplicatesMainAndEnvEntries(): void
    {
        putenv('MAIN_DOMAIN=Example.com');
        $_ENV['MAIN_DOMAIN'] = 'Example.com';
        putenv('MARKETING_DOMAINS=promo.example.com example.com');
        $_ENV['MARKETING_DOMAINS'] = 'promo.example.com example.com';

        $provider = $this->createProvider();
        $service = new CertificateProvisioningService($provider);

        $method = new ReflectionMethod(CertificateProvisioningService::class, 'collectMarketingDomains');
        $method->setAccessible(true);

        $domains = $method->invoke($service, 'promo.example.com');

        self::assertSame(['example.com', 'promo.example.com'], $domains);
    }

    public function testCollectMarketingDomainsKeepsAdminAndBaseVariants(): void
    {
        putenv('MARKETING_DOMAINS=admin.example.com');
        $_ENV['MARKETING_DOMAINS'] = 'admin.example.com';

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);

        $provider = $this->createProvider();
        $service = new CertificateProvisioningService($provider);

        $method = new ReflectionMethod(CertificateProvisioningService::class, 'collectMarketingDomains');
        $method->setAccessible(true);

        $domains = $method->invoke($service);

        self::assertSame(['admin.example.com', 'example.com'], $domains);
    }

    public function testEnvDomainsSupplementDatabaseEntries(): void
    {
        putenv('MARKETING_DOMAINS=promo.example.com');
        $_ENV['MARKETING_DOMAINS'] = 'promo.example.com';

        $provider = $this->createProviderWithDomains(['shop.example.com']);

        self::assertSame(
            ['shop.example.com', 'promo.example.com'],
            $provider->getMarketingDomains()
        );
    }

    private function createProvider(): MarketingDomainProvider
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE marketing_domains (host TEXT, normalized_host TEXT UNIQUE)');

        return new MarketingDomainProvider(static fn (): PDO => $pdo, 0);
    }

    private function createProviderWithDomains(array $domains): MarketingDomainProvider
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE marketing_domains (host TEXT, normalized_host TEXT UNIQUE)');
        $stmt = $pdo->prepare('INSERT INTO marketing_domains (host, normalized_host) VALUES (:host, :normalized)');

        foreach ($domains as $host) {
            $stmt->execute([
                ':host' => $host,
                ':normalized' => DomainNameHelper::normalize($host, stripAdmin: false),
            ]);
        }

        return new MarketingDomainProvider(static fn (): PDO => $pdo, 0);
    }

    private function restoreEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key]);

            return;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
