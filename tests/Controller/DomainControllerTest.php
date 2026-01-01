<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\CertificateZoneRegistry;
use App\Service\DomainService;
use App\Service\MarketingDomainProvider;
use App\Support\DomainNameHelper;
use Tests\TestCase;

class DomainControllerTest extends TestCase
{
    public function testUpdateAcceptsJsonWithCharset(): void
    {
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

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
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT)');

        $this->setDatabase($pdo);

        $service = new DomainService($pdo);
        $domain = $service->createDomain('example.com', 'Example', null, true);

        $request = $this->createRequest(
            'PATCH',
            '/admin/domains/api/' . $domain['id'],
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $payload = json_encode([
            'host' => 'example.com',
            'label' => 'Updated label',
            'namespace' => null,
            'is_active' => true,
        ], JSON_THROW_ON_ERROR);

        $request->getBody()->write($payload);
        $request->getBody()->rewind();

        $controller = new \App\Controller\Admin\DomainController($service, new CertificateZoneRegistry($pdo));
        $response = $controller->update($request, new \Slim\Psr7\Response(), ['id' => (string) $domain['id']]);

        $this->assertSame(200, $response->getStatusCode());

        $updated = $service->getDomainById($domain['id']);
        $this->assertNotNull($updated);
        $this->assertSame('Updated label', $updated['label']);
    }

    public function testUpdateHandlesParsedObjectBody(): void
    {
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

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
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT)');

        $this->setDatabase($pdo);

        $service = new DomainService($pdo);
        $domain = $service->createDomain('example.com', 'Example', null, true);

        $controller = new \App\Controller\Admin\DomainController($service, new CertificateZoneRegistry($pdo));

        $request = $this->createRequest(
            'PATCH',
            '/admin/domains/api/' . $domain['id'],
            [
                'Content-Type' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        )->withParsedBody((object) [
            'host' => 'example.com',
            'label' => 'Updated again',
            'namespace' => null,
            'is_active' => true,
        ]);

        $response = $controller->update($request, new \Slim\Psr7\Response(), ['id' => (string) $domain['id']]);

        $this->assertSame(200, $response->getStatusCode());

        $updated = $service->getDomainById($domain['id']);
        $this->assertNotNull($updated);
        $this->assertSame('Updated again', $updated['label']);
    }

    public function testRenewSslQueuesSingleDomain(): void
    {
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

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
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT)');

        $this->setDatabase($pdo);

        $service = new DomainService($pdo);
        $registry = new CertificateZoneRegistry($pdo);
        $domain = $service->createDomain('WWW.Example.com', 'Example', null, true);
        $registry->ensureZone($domain['zone']);

        $controller = new \App\Controller\Admin\DomainController($service, $registry);

        $request = $this->createRequest(
            'POST',
            '/admin/domains/api/' . $domain['id'] . '/renew',
            [
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $controller->renewSsl($request, new \Slim\Psr7\Response(), ['id' => (string) $domain['id']]);

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertSame('Certificate renewal queued.', $payload['status'] ?? null);
        $this->assertSame('example.com', $payload['domain'] ?? null);

        $status = $pdo->query('SELECT status FROM certificate_zones WHERE zone = "example.com"');
        $this->assertSame('pending', $status !== false ? $status->fetchColumn() : null);
    }

    public function testProvisioningUsesFreshMarketingDomains(): void
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
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT)');

        $this->setDatabase($pdo);

        $service = new DomainService($pdo);
        $registry = new CertificateZoneRegistry($pdo);

        $provider = new class (static fn (): \PDO => $pdo) extends MarketingDomainProvider {
            public bool $cleared = false;

            public function clearCache(): void
            {
                $this->cleared = true;
                parent::clearCache();
            }
        };

        $previousProvider = DomainNameHelper::getMarketingDomainProvider();
        DomainNameHelper::setMarketingDomainProvider($provider);

        try {
            $controller = new \App\Controller\Admin\DomainController($service, $registry);

            $request = $this->createRequest(
                'POST',
                '/admin/domains/api',
                [
                    'HTTP_ACCEPT' => 'application/json',
                ]
            )->withParsedBody([
                'host' => 'fresh.example.com',
                'is_active' => true,
            ]);

            $response = $controller->create($request, new \Slim\Psr7\Response());

            $this->assertSame(201, $response->getStatusCode());
            $this->assertTrue($provider->cleared);

            $status = $pdo->query('SELECT status FROM certificate_zones WHERE zone = "example.com"');
            $this->assertSame('pending', $status !== false ? $status->fetchColumn() : null);
        } finally {
            DomainNameHelper::setMarketingDomainProvider($previousProvider);
        }
    }

    public function testProvisionSslAllowsDomainsWithoutNamespace(): void
    {
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

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
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT)');

        $this->setDatabase($pdo);

        $service = new DomainService($pdo);
        $registry = new CertificateZoneRegistry($pdo);
        $domain = $service->createDomain('promo.example.com', 'Example', null, true);

        $controller = new \App\Controller\Admin\DomainController($service, $registry);

        $request = $this->createRequest(
            'POST',
            '/api/admin/domains/' . $domain['id'] . '/provision-ssl',
            [
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $controller->provisionSsl($request, new \Slim\Psr7\Response(), ['id' => (string) $domain['id']]);

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('started', $payload['status'] ?? null);
        $this->assertNull($payload['namespace'] ?? null);
        $this->assertSame('promo.example.com', $payload['domain'] ?? null);

        $zoneStmt = $pdo->prepare('SELECT status FROM certificate_zones WHERE zone = ?');
        $zoneStmt->execute([$domain['zone']]);
        $this->assertSame('pending', $zoneStmt->fetchColumn());
    }

    public function testDeleteRemovesZoneAndWildcardConfigs(): void
    {
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

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
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT)');

        $this->setDatabase($pdo);

        $configDir = sys_get_temp_dir() . '/wildcard-configs-delete-' . uniqid('', true);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        $service = new DomainService($pdo);
        $registry = new CertificateZoneRegistry($pdo);
        $domain = $service->createDomain('Example.com', 'Example', null, true);
        $registry->ensureZone($domain['zone']);

        $configFile = $configDir . '/' . $domain['zone'] . '.conf';
        file_put_contents($configFile, 'active');

        $controller = new \App\Controller\Admin\DomainController($service, $registry);

        $request = $this->createRequest(
            'DELETE',
            '/admin/domains/api/' . $domain['id'],
            [
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $controller->delete($request, new \Slim\Psr7\Response(), ['id' => (string) $domain['id']]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM certificate_zones')->fetchColumn());

        $this->synchronizeWildcardConfigs($registry, $configDir);
        $this->assertFileDoesNotExist($configFile);

        $this->removeDirectory($configDir);
    }

    public function testDeactivatingLastDomainRemovesZoneAndConfigs(): void
    {
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

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
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT)');

        $this->setDatabase($pdo);

        $configDir = sys_get_temp_dir() . '/wildcard-configs-deactivate-' . uniqid('', true);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        $service = new DomainService($pdo);
        $registry = new CertificateZoneRegistry($pdo);
        $domain = $service->createDomain('example.com', 'Example', null, true);
        $registry->ensureZone($domain['zone']);

        $configFile = $configDir . '/' . $domain['zone'] . '.conf';
        file_put_contents($configFile, 'active');

        $controller = new \App\Controller\Admin\DomainController($service, $registry);

        $request = $this->createRequest(
            'PATCH',
            '/admin/domains/api/' . $domain['id'],
            [
                'Content-Type' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $payload = json_encode([
            'host' => 'example.com',
            'label' => 'Example',
            'namespace' => null,
            'is_active' => false,
        ], JSON_THROW_ON_ERROR);

        $request->getBody()->write($payload);
        $request->getBody()->rewind();

        $response = $controller->update($request, new \Slim\Psr7\Response(), ['id' => (string) $domain['id']]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($service->getDomainById($domain['id'])['is_active']);
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM certificate_zones')->fetchColumn());

        $this->synchronizeWildcardConfigs($registry, $configDir);
        $this->assertFileDoesNotExist($configFile);

        $this->removeDirectory($configDir);
    }

    private function synchronizeWildcardConfigs(CertificateZoneRegistry $registry, string $configDir): void
    {
        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        $zones = $registry->listWildcardEnabled();
        $activeFiles = [];

        foreach ($zones as $zone) {
            $activeFiles[] = $configDir . '/' . $zone['zone'] . '.conf';
        }

        $existing = glob($configDir . '/*.conf') ?: [];
        foreach ($existing as $file) {
            if (!in_array($file, $activeFiles, true)) {
                @unlink($file);
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = glob($path . '/*') ?: [];
        foreach ($entries as $entry) {
            if (is_dir($entry)) {
                $this->removeDirectory($entry);
                continue;
            }

            @unlink($entry);
        }

        @rmdir($path);
    }
}
