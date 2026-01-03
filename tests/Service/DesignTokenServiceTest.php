<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ConfigService;
use App\Service\DesignTokenService;
use PDO;
use Tests\TestCase;

class DesignTokenServiceTest extends TestCase
{
    public function testPersistTokensCreatesNamespaceAndCss(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY, design_tokens TEXT)');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE namespaces(
                namespace TEXT PRIMARY KEY,
                label TEXT,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TEXT,
                updated_at TEXT
            )
            SQL
        );

        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';

        $cssPath = tempnam(sys_get_temp_dir(), 'namespace-tokens-');
        $configService = new ConfigService($pdo);
        $service = new DesignTokenService($pdo, $configService, $cssPath);

        $tokens = $service->persistTokens('New-Namespace', [
            'brand' => ['primary' => '#222222', 'accent' => '#fa6400'],
            'layout' => ['profile' => 'wide'],
        ]);

        $namespace = $pdo->query(
            "SELECT namespace FROM namespaces WHERE namespace = 'new-namespace'"
        )->fetchColumn();
        $configTokens = $pdo->query(
            "SELECT design_tokens FROM config WHERE event_uid = 'new-namespace'"
        )->fetchColumn();
        $stylesheet = file_get_contents($cssPath);

        $this->assertSame('new-namespace', $namespace);
        $this->assertNotFalse($configTokens);
        $this->assertSame('#222222', $tokens['brand']['primary']);
        $this->assertStringContainsString('[data-namespace="new-namespace"]', (string) $stylesheet);
        $this->assertStringContainsString('--brand-primary: #222222', (string) $stylesheet);

        putenv('DASHBOARD_TOKEN_SECRET');
        unset($_ENV['DASHBOARD_TOKEN_SECRET']);
    }
}
