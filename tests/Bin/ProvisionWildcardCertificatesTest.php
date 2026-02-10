<?php

declare(strict_types=1);

namespace Tests\Bin;

use App\Service\CertificateZoneRegistry;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProvisionWildcardCertificatesTest extends TestCase
{
    private string $workspace;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->workspace = sys_get_temp_dir() . '/wildcard-' . bin2hex(random_bytes(6));

        if (!mkdir($concurrentDirectory = $this->workspace, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create test workspace.');
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);

        parent::tearDown();
    }

    public function testMaintenanceSkipsFreshCertificates(): void
    {
        [$dbPath, $certDir, $logFile, $acmeBinary] = $this->prepareFilesystem();
        $pdo = $this->bootstrapDatabase($dbPath);

        $recentIssue = new DateTimeImmutable('-10 days');
        $this->seedData($pdo, [
            [
                'zone' => 'example.com',
                'status' => CertificateZoneRegistry::STATUS_ISSUED,
                'last_issued_at' => $recentIssue,
            ],
        ]);

        $this->createCertificate($certDir . '/example.com/fullchain.pem', 90);

        $output = $this->runProvisioning($dbPath, $certDir, $logFile, $acmeBinary);

        $this->assertStringContainsString('Skipping example.com', $output);
        $this->assertSame('', trim((string) file_get_contents($logFile)));
    }

    public function testMaintenanceRenewsWhenWindowReached(): void
    {
        [$dbPath, $certDir, $logFile, $acmeBinary] = $this->prepareFilesystem();
        $pdo = $this->bootstrapDatabase($dbPath);

        $staleIssue = new DateTimeImmutable('-70 days');
        $this->seedData($pdo, [
            [
                'zone' => 'example.com',
                'status' => CertificateZoneRegistry::STATUS_ISSUED,
                'last_issued_at' => $staleIssue,
            ],
        ]);

        $output = $this->runProvisioning($dbPath, $certDir, $logFile, $acmeBinary);

        $logContents = trim((string) file_get_contents($logFile));
        $this->assertStringContainsString('Renewing example.com (window reached)', $output);
        $this->assertStringContainsString('--issue --dns dns_hetzner -d example.com -d *.example.com', $logContents);
        $this->assertStringContainsString('--install-cert -d example.com --fullchain-file', $logContents);

        $row = $pdo->query('SELECT status, next_renewal_after FROM certificate_zones WHERE zone = "example.com"');
        $data = $row !== false ? $row->fetch(PDO::FETCH_ASSOC) : null;
        $this->assertSame('issued', $data['status']);

        $nextRenewal = new DateTimeImmutable((string) $data['next_renewal_after']);
        $this->assertGreaterThan(50, (int) (new DateTimeImmutable())->diff($nextRenewal)->format('%a'));
    }

    public function testMaintenanceHonorsQueuedRenewal(): void
    {
        [$dbPath, $certDir, $logFile, $acmeBinary] = $this->prepareFilesystem();
        $pdo = $this->bootstrapDatabase($dbPath);

        $recentIssue = new DateTimeImmutable('-10 days');
        $this->seedData($pdo, [
            [
                'zone' => 'forced.example.com',
                'status' => CertificateZoneRegistry::STATUS_PENDING,
                'last_issued_at' => $recentIssue,
            ],
        ]);

        $output = $this->runProvisioning($dbPath, $certDir, $logFile, $acmeBinary);

        $logContents = trim((string) file_get_contents($logFile));
        $this->assertStringContainsString('Renewing forced.example.com (queued)', $output);
        $this->assertStringContainsString('--issue --dns dns_hetzner -d forced.example.com -d *.forced.example.com', $logContents);
    }

    /**
     * @return array{string,string,string,string}
     */
    private function prepareFilesystem(): array
    {
        $dbPath = $this->workspace . '/data.sqlite';
        $certDir = $this->workspace . '/certs';
        $logFile = $this->workspace . '/acme.log';
        $acmeBinary = $this->workspace . '/acme.sh';

        file_put_contents($logFile, '');
        if (!mkdir($certDir, 0775, true) && !is_dir($certDir)) {
            throw new RuntimeException('Unable to create certificate directory.');
        }

        $this->createFakeAcmeBinary($acmeBinary, $logFile);

        return [$dbPath, $certDir, $logFile, $acmeBinary];
    }

    private function bootstrapDatabase(string $dbPath): PDO
    {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

        return $pdo;
    }

    /**
     * @param list<array{zone:string,status:string,last_issued_at:?DateTimeImmutable}> $zones
     */
    private function seedData(PDO $pdo, array $zones): void
    {
        $domainStmt = $pdo->prepare('INSERT INTO domains (host, normalized_host, zone, namespace, label, is_active) VALUES (?, ?, ?, NULL, NULL, 1)');
        $zoneStmt = $pdo->prepare('INSERT INTO certificate_zones (zone, provider, wildcard_enabled, status, last_issued_at, last_error, next_renewal_after) VALUES (?, ?, 1, ?, ?, NULL, NULL)');

        foreach ($zones as $zone) {
            $normalized = strtolower($zone['zone']);
            $domainStmt->execute([$normalized, $normalized, $normalized]);
            $zoneStmt->execute([
                $normalized,
                'hetzner',
                $zone['status'],
                $zone['last_issued_at']?->format(DateTimeImmutable::ATOM),
            ]);
        }
    }

    private function createCertificate(string $fullchainPath, int $validDays): void
    {
        $directory = dirname($fullchainPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create certificate directory for test.');
        }

        $keyPath = $directory . '/key.pem';
        $command = sprintf(
            'openssl req -x509 -nodes -newkey rsa:2048 -keyout %s -out %s -days %d -subj "/CN=example.com"',
            escapeshellarg($keyPath),
            escapeshellarg($fullchainPath),
            $validDays
        );

        exec($command, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('Failed to generate test certificate.');
        }
    }

    private function createFakeAcmeBinary(string $path, string $logFile): void
    {
        $script = <<<BASH
#!/usr/bin/env bash
echo "$@" >> "$logFile"
exit 0
BASH;

        file_put_contents($path, $script);
        chmod($path, 0755);
    }

    private function runProvisioning(string $dbPath, string $certDir, string $logFile, string $acmeBinary): string
    {
        $env = array_merge(getenv(), [
            'POSTGRES_DSN' => 'sqlite:' . $dbPath,
            'POSTGRES_USER' => '',
            'POSTGRES_PASSWORD' => '',
            'ACME_SH_BIN' => $acmeBinary,
            'ACME_WILDCARD_PROVIDER' => 'dns_hetzner',
            'NGINX_WILDCARD_CERT_DIR' => $certDir,
            'ACME_RELOAD_ON_SUCCESS' => '0',
            'ACME_LOG' => $logFile,
        ]);

        $process = proc_open(
            [PHP_BINARY, $this->projectRoot . '/bin/provision-wildcard-certificates'],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $this->projectRoot,
            $env
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to execute provisioning script.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);

        return (string) $stdout . (string) $stderr;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
