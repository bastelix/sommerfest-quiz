<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Admin\DomainStartPageController;
use App\Infrastructure\Migrations\Migrator;
use App\Service\CertificateProvisioningService;
use App\Service\DomainStartPageService;
use App\Service\MarketingDomainProvider;
use App\Service\NginxService;
use App\Service\PageService;
use App\Service\ReverseProxyHostUpdater;
use App\Service\SettingsService;
use App\Support\DomainNameHelper;
use PDO;
use Slim\Psr7\Response;
use Tests\TestCase;

use function file_get_contents;
use function preg_match_all;

final class TrackingMarketingDomainProvider extends MarketingDomainProvider
{
    public int $clearCount = 0;

    public function clearCache(): void
    {
        $this->clearCount++;
        parent::clearCache();
    }
}

class AdminDomainStartPageControllerTest extends TestCase
{
    public function testAdminCanCreateMarketingDomain(): void
    {
        $this->bootMinimalSchema();

        $pdo = $this->getDatabase();
        $service = new DomainStartPageService($pdo);
        [$envFile, $proxyUpdater] = $this->buildProxyUpdater($service);
        $marketingDomainProvider = new TrackingMarketingDomainProvider(static fn (): PDO => $pdo, 0);
        $controller = new DomainStartPageController(
            $service,
            new CertificateProvisioningService($service),
            $proxyUpdater,
            new SettingsService($pdo),
            new PageService($pdo),
            $marketingDomainProvider
        );

        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        putenv('MARKETING_DOMAINS=');
        $_ENV['MARKETING_DOMAINS'] = '';

        $request = $this->createRequest('POST', '/admin/marketing-domains', [
            'Content-Type' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $payload = json_encode([
            'domain' => 'promo.example.com',
            'label' => 'Promo',
        ], JSON_THROW_ON_ERROR);
        $request->getBody()->write($payload);
        $request->getBody()->rewind();

        try {
            $response = $controller->createMarketingDomain($request, new Response());
        } finally {
            Migrator::setHook(null);
            @unlink($envFile);
        }

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $data['status'] ?? null);
        $this->assertArrayHasKey('domain', $data);
        $this->assertSame('promo.example.com', $data['domain']['host'] ?? null);
        $this->assertArrayHasKey('marketing_domains', $data);
        $this->assertCount(1, $data['marketing_domains']);

        $domains = $service->listMarketingDomains();
        $this->assertCount(1, $domains);
        $this->assertSame('promo.example.com', $domains[0]['host']);
        $this->assertSame('promo.example.com', $domains[0]['normalized_host']);
        $this->assertSame('Promo', $domains[0]['label']);
        $this->assertSame(1, $marketingDomainProvider->clearCount);
    }

    public function testAdminCanDeleteMarketingDomain(): void
    {
        $this->bootMinimalSchema();

        $pdo = $this->getDatabase();
        $service = new DomainStartPageService($pdo);
        [$envFile, $proxyUpdater] = $this->buildProxyUpdater($service);
        $marketingDomainProvider = new TrackingMarketingDomainProvider(static fn (): PDO => $pdo, 0);
        $controller = new DomainStartPageController(
            $service,
            new CertificateProvisioningService($service),
            $proxyUpdater,
            new SettingsService($pdo),
            new PageService($pdo),
            $marketingDomainProvider
        );

        $created = $service->createMarketingDomain('promo.example.com', 'Promo');

        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        putenv('MARKETING_DOMAINS=');
        $_ENV['MARKETING_DOMAINS'] = '';

        $request = $this->createRequest('DELETE', '/admin/marketing-domains/' . $created['id'], [
            'Content-Type' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        try {
            $response = $controller->deleteMarketingDomain($request, new Response(), ['id' => (string) $created['id']]);
        } finally {
            Migrator::setHook(null);
            @unlink($envFile);
        }

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $data['status'] ?? null);
        $this->assertArrayHasKey('marketing_domains', $data);
        $this->assertCount(0, $data['marketing_domains']);

        $domains = $service->listMarketingDomains();
        $this->assertCount(0, $domains);
        $this->assertSame(1, $marketingDomainProvider->clearCount);
    }

    public function testAdminCanUpdateMarketingDomainAndClearCache(): void
    {
        $this->bootMinimalSchema();

        $pdo = $this->getDatabase();
        $service = new DomainStartPageService($pdo);
        [$envFile, $proxyUpdater] = $this->buildProxyUpdater($service);
        $marketingDomainProvider = new TrackingMarketingDomainProvider(static fn (): PDO => $pdo, 0);
        $controller = new DomainStartPageController(
            $service,
            new CertificateProvisioningService($service),
            $proxyUpdater,
            new SettingsService($pdo),
            new PageService($pdo),
            $marketingDomainProvider
        );

        $created = $service->createMarketingDomain('promo.example.com', 'Promo');

        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        putenv('MARKETING_DOMAINS=');
        $_ENV['MARKETING_DOMAINS'] = '';

        $request = $this->createRequest('PUT', '/admin/marketing-domains/' . $created['id'], [
            'Content-Type' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $payload = json_encode([
            'domain' => 'launch.example.com',
            'label' => 'Launch',
        ], JSON_THROW_ON_ERROR);
        $request->getBody()->write($payload);
        $request->getBody()->rewind();

        try {
            $response = $controller->updateMarketingDomain($request, new Response(), ['id' => (string) $created['id']]);
        } finally {
            Migrator::setHook(null);
            @unlink($envFile);
        }

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $data['status'] ?? null);
        $this->assertSame('launch.example.com', $data['domain']['host'] ?? null);
        $this->assertSame('Launch', $data['domain']['label'] ?? null);
        $this->assertCount(1, $data['marketing_domains'] ?? []);
        $this->assertSame(1, $marketingDomainProvider->clearCount);
    }

    public function testAdminCanReconcileMarketingDomains(): void
    {
        $this->bootMinimalSchema();

        $pdo = $this->getDatabase();
        $service = new DomainStartPageService($pdo);
        [$envFile, $proxyUpdater] = $this->buildProxyUpdater($service);
        $marketingDomainProvider = new TrackingMarketingDomainProvider(static fn (): PDO => $pdo, 0);
        $controller = new DomainStartPageController(
            $service,
            new CertificateProvisioningService($service),
            $proxyUpdater,
            new SettingsService($pdo),
            new PageService($pdo),
            $marketingDomainProvider
        );

        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        putenv('MARKETING_DOMAINS=');
        $_ENV['MARKETING_DOMAINS'] = '';

        $this->insertMarketingDomain($pdo, 'promo.example.com');
        $marketingDomainProvider->getMarketingDomains(stripAdmin: false);
        $this->insertMarketingDomain($pdo, 'launch.example.com');

        $request = $this->createRequest('POST', '/admin/marketing-domains/reconcile', [
            'Content-Type' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        try {
            $response = $controller->reconcileMarketingDomains($request, new Response());
        } finally {
            Migrator::setHook(null);
            @unlink($envFile);
        }

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $data['status'] ?? null);
        $this->assertSame(['launch.example.com'], $data['provisioned'] ?? []);
        $this->assertCount(2, $data['marketing_domains'] ?? []);
        $this->assertContains('promo.example.com', $data['resolved_marketing_domains'] ?? []);
        $this->assertContains('launch.example.com', $data['resolved_marketing_domains'] ?? []);
        $this->assertSame(1, $marketingDomainProvider->clearCount);
    }

    private function insertMarketingDomain(PDO $pdo, string $host): void
    {
        $normalized = DomainNameHelper::normalize($host, stripAdmin: false);
        $stmt = $pdo->prepare('INSERT INTO marketing_domains (host, normalized_host) VALUES (:host, :normalized)');
        $stmt->execute([
            ':host' => $host,
            ':normalized' => $normalized,
        ]);
    }

    /**
     * @return array{0:string,1:ReverseProxyHostUpdater}
     */
    private function buildProxyUpdater(DomainStartPageService $service): array
    {
        $envFile = tempnam(sys_get_temp_dir(), 'quiz-env-');
        if ($envFile === false) {
            throw new \RuntimeException('Unable to create a temporary environment file.');
        }

        if (file_put_contents($envFile, '') === false) {
            throw new \RuntimeException('Unable to initialize temporary environment file.');
        }

        putenv('NGINX_RELOAD=0');
        $_ENV['NGINX_RELOAD'] = '0';

        $updater = new ReverseProxyHostUpdater($service, new NginxService(), $envFile);

        return [$envFile, $updater];
    }

    private function bootMinimalSchema(): void
    {
        Migrator::setHook(static function (PDO $pdo): bool {
            $schemaPath = __DIR__ . '/../../../src/Infrastructure/Migrations/sqlite-schema.sql';
            $schema = file_get_contents($schemaPath);
            if ($schema === false) {
                throw new \RuntimeException('Unable to load SQLite schema.');
            }

            preg_match_all('/(CREATE TABLE[\s\S]*?;)/', $schema, $tableMatches);
            foreach ($tableMatches[1] as $statement) {
                $pdo->exec($statement);
            }

            preg_match_all('/(CREATE (?:UNIQUE )?INDEX[\s\S]*?;)/', $schema, $indexMatches);
            foreach ($indexMatches[1] as $statement) {
                $pdo->exec($statement);
            }

            $pdo->exec('CREATE TABLE IF NOT EXISTS marketing_domains ('
                . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
                . 'host TEXT NOT NULL, '
                . 'normalized_host TEXT NOT NULL UNIQUE, '
                . 'label TEXT, '
                . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP, '
                . 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP'
                . ')');

            $pdo->exec("INSERT OR IGNORE INTO settings(key, value) VALUES('home_page', 'help')");
            $pdo->exec("INSERT OR IGNORE INTO settings(key, value) VALUES('registration_enabled', '0')");

            return false;
        });
    }
}
