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
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT, next_renewal_after TEXT)');

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
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT, next_renewal_after TEXT)');

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
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT, next_renewal_after TEXT)');

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
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT, next_renewal_after TEXT)');

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
        $pdo->exec('CREATE TABLE certificate_zones (zone TEXT PRIMARY KEY, provider TEXT, wildcard_enabled INTEGER, status TEXT, last_issued_at TEXT, last_error TEXT, next_renewal_after TEXT)');

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

        $zoneStmt = $pdo->query('SELECT status FROM certificate_zones WHERE zone = "example.com"');
        $status = $zoneStmt !== false ? $zoneStmt->fetchColumn() : false;
        $this->assertSame('pending', $status);
    }

    public function testDeletingLastDomainRemovesCertificateZoneAndConfig(): void
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'domains-');
        $this->assertNotFalse($dbFile);

        $pdo = new \PDO('sqlite:' . $dbFile);
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
        $domain = $service->createDomain('example.com', 'Example', null, true);
        $registry->ensureZone($domain['zone']);

        $confDir = sys_get_temp_dir() . '/nginx-conf-' . uniqid('', true);
        $certDir = $confDir . '/certs';
        mkdir($confDir, 0777, true);
        mkdir($certDir, 0777, true);
        file_put_contents($confDir . '/example.com.conf', 'legacy');

        $binDir = sys_get_temp_dir() . '/nginx-bin-' . uniqid('', true);
        mkdir($binDir, 0777, true);
        $nginx = $binDir . '/nginx';
        file_put_contents($nginx, "#!/usr/bin/env bash\nexit 0\n");
        chmod($nginx, 0755);

        $originalPath = getenv('PATH') ?: '';
        $envBackup = [
            'NGINX_WILDCARD_CONF_DIR' => getenv('NGINX_WILDCARD_CONF_DIR'),
            'NGINX_WILDCARD_CERT_DIR' => getenv('NGINX_WILDCARD_CERT_DIR'),
            'NGINX_WILDCARD_UPSTREAM' => getenv('NGINX_WILDCARD_UPSTREAM'),
            'POSTGRES_DSN' => getenv('POSTGRES_DSN'),
            'POSTGRES_USER' => getenv('POSTGRES_USER'),
            'POSTGRES_PASSWORD' => getenv('POSTGRES_PASSWORD'),
            'PATH' => $originalPath,
        ];

        putenv('NGINX_WILDCARD_CONF_DIR=' . $confDir);
        putenv('NGINX_WILDCARD_CERT_DIR=' . $certDir);
        putenv('NGINX_WILDCARD_UPSTREAM=http://localhost');
        putenv('POSTGRES_DSN=sqlite:' . $dbFile);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        putenv('PATH=' . $binDir . PATH_SEPARATOR . $originalPath);

        try {
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
            $this->assertSame('0', (string) $pdo->query('SELECT COUNT(*) FROM certificate_zones')->fetchColumn());

            exec('php bin/generate-nginx-zones', $output, $exitCode);
            $this->assertSame(0, $exitCode);
            $this->assertFileDoesNotExist($confDir . '/example.com.conf');
        } finally {
            $this->restoreEnv($envBackup);
            $this->removeDirectory($confDir);
            $this->removeDirectory($binDir);
            @unlink($dbFile);
        }
    }

    public function testDeactivatingLastDomainRemovesCertificateZoneAndConfig(): void
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'domains-');
        $this->assertNotFalse($dbFile);

        $pdo = new \PDO('sqlite:' . $dbFile);
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
        $domain = $service->createDomain('example.com', 'Example', null, true);
        $registry->ensureZone($domain['zone']);

        $confDir = sys_get_temp_dir() . '/nginx-conf-' . uniqid('', true);
        $certDir = $confDir . '/certs';
        mkdir($confDir, 0777, true);
        mkdir($certDir, 0777, true);
        file_put_contents($confDir . '/example.com.conf', 'legacy');

        $binDir = sys_get_temp_dir() . '/nginx-bin-' . uniqid('', true);
        mkdir($binDir, 0777, true);
        $nginx = $binDir . '/nginx';
        file_put_contents($nginx, "#!/usr/bin/env bash\nexit 0\n");
        chmod($nginx, 0755);

        $originalPath = getenv('PATH') ?: '';
        $envBackup = [
            'NGINX_WILDCARD_CONF_DIR' => getenv('NGINX_WILDCARD_CONF_DIR'),
            'NGINX_WILDCARD_CERT_DIR' => getenv('NGINX_WILDCARD_CERT_DIR'),
            'NGINX_WILDCARD_UPSTREAM' => getenv('NGINX_WILDCARD_UPSTREAM'),
            'POSTGRES_DSN' => getenv('POSTGRES_DSN'),
            'POSTGRES_USER' => getenv('POSTGRES_USER'),
            'POSTGRES_PASSWORD' => getenv('POSTGRES_PASSWORD'),
            'PATH' => $originalPath,
        ];

        putenv('NGINX_WILDCARD_CONF_DIR=' . $confDir);
        putenv('NGINX_WILDCARD_CERT_DIR=' . $certDir);
        putenv('NGINX_WILDCARD_UPSTREAM=http://localhost');
        putenv('POSTGRES_DSN=sqlite:' . $dbFile);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        putenv('PATH=' . $binDir . PATH_SEPARATOR . $originalPath);

        try {
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
            $this->assertSame('0', (string) $pdo->query('SELECT COUNT(*) FROM certificate_zones')->fetchColumn());

            exec('php bin/generate-nginx-zones', $output, $exitCode);
            $this->assertSame(0, $exitCode);
            $this->assertFileDoesNotExist($confDir . '/example.com.conf');
        } finally {
            $this->restoreEnv($envBackup);
            $this->removeDirectory($confDir);
            $this->removeDirectory($binDir);
            @unlink($dbFile);
        }
    }

    /**
     * @param array<string,string|false> $envBackup
     */
    private function restoreEnv(array $envBackup): void
    {
        foreach ($envBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
                continue;
            }

            putenv($key . '=' . $value);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
