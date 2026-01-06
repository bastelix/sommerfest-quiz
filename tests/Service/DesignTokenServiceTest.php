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
        $namespacedCss = dirname($cssPath) . '/new-namespace/namespace-tokens.css';
        $namespacedStylesheet = is_string($namespacedCss) ? @file_get_contents($namespacedCss) : false;

        $this->assertSame('new-namespace', $namespace);
        $this->assertNotFalse($configTokens);
        $this->assertSame('#222222', $tokens['brand']['primary']);
        $this->assertStringContainsString('[data-namespace="new-namespace"]', (string) $stylesheet);
        $this->assertStringContainsString('--brand-primary: #222222', (string) $stylesheet);
        $this->assertNotFalse($namespacedStylesheet);
        $this->assertStringContainsString("@import '../namespace-tokens.css';", (string) $namespacedStylesheet);

        putenv('DASHBOARD_TOKEN_SECRET');
        unset($_ENV['DASHBOARD_TOKEN_SECRET']);
    }

    public function testBuildCssIncludesNamespacedBlocksWithMergedTokens(): void
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

        $service->persistTokens('default', [
            'brand' => ['primary' => '#111111'],
            'components' => ['buttonStyle' => 'ghost'],
        ]);
        $service->persistTokens('calserver', [
            'brand' => ['accent' => '#222222'],
        ]);

        $stylesheet = file_get_contents($cssPath);

        $this->assertNotFalse($stylesheet);
        $this->assertStringContainsString(':root {', (string) $stylesheet);
        $this->assertStringContainsString('--brand-primary: #111111', (string) $stylesheet);
        $this->assertStringContainsString('[data-namespace="calserver"]', (string) $stylesheet);
        $this->assertStringContainsString("[data-namespace=\"calserver\"] {\n  --brand-primary: #111111", (string) $stylesheet);
        $this->assertStringContainsString('--brand-accent: #222222', (string) $stylesheet);
        $this->assertStringContainsString('--components-button-style: ghost', (string) $stylesheet);

        putenv('DASHBOARD_TOKEN_SECRET');
        unset($_ENV['DASHBOARD_TOKEN_SECRET']);
    }

    public function testFallsBackToDefaultNamespaceTokens(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY, design_tokens TEXT)');

        $defaultTokens = [
            'brand' => ['primary' => '#111111', 'accent' => '#222222'],
            'layout' => ['profile' => 'wide'],
            'typography' => ['preset' => 'classic'],
            'components' => ['cardStyle' => 'square', 'buttonStyle' => 'ghost'],
        ];

        $pdo->prepare('INSERT INTO config(event_uid, design_tokens) VALUES(?, ?)')
            ->execute(['default', json_encode($defaultTokens, JSON_THROW_ON_ERROR)]);

        $configService = new ConfigService($pdo);
        $service = new DesignTokenService($pdo, $configService, tempnam(sys_get_temp_dir(), 'namespace-tokens-'));

        $tokens = $service->getTokensForNamespace('partner');

        $this->assertSame('#111111', $tokens['brand']['primary']);
        $this->assertSame('wide', $tokens['layout']['profile']);
        $this->assertSame('classic', $tokens['typography']['preset']);

        $count = $pdo->query("SELECT COUNT(*) FROM config WHERE event_uid = 'partner'")->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    public function testNamespaceTokensOverrideDefaults(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY, design_tokens TEXT)');

        $defaultTokens = [
            'brand' => ['primary' => '#010101', 'accent' => '#020202'],
            'layout' => ['profile' => 'wide'],
            'typography' => ['preset' => 'modern'],
            'components' => ['cardStyle' => 'rounded', 'buttonStyle' => 'ghost'],
        ];
        $namespaceTokens = [
            'brand' => ['primary' => '#111111'],
            'components' => ['cardStyle' => 'square'],
        ];

        $pdo->prepare('INSERT INTO config(event_uid, design_tokens) VALUES(?, ?)')
            ->execute(['default', json_encode($defaultTokens, JSON_THROW_ON_ERROR)]);
        $pdo->prepare('INSERT INTO config(event_uid, design_tokens) VALUES(?, ?)')
            ->execute(['namespace-a', json_encode($namespaceTokens, JSON_THROW_ON_ERROR)]);

        $configService = new ConfigService($pdo);
        $service = new DesignTokenService($pdo, $configService, tempnam(sys_get_temp_dir(), 'namespace-tokens-'));

        $tokens = $service->getTokensForNamespace('namespace-a');

        $this->assertSame('#111111', $tokens['brand']['primary']);
        $this->assertSame('#020202', $tokens['brand']['accent']);
        $this->assertSame('wide', $tokens['layout']['profile']);
        $this->assertSame('square', $tokens['components']['cardStyle']);
        $this->assertSame('ghost', $tokens['components']['buttonStyle']);
    }

    public function testFactoryDefaultsUsedOnlyWhenNoDesignExists(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY, design_tokens TEXT)');

        $configService = new ConfigService($pdo);
        $service = new DesignTokenService($pdo, $configService, tempnam(sys_get_temp_dir(), 'namespace-tokens-'));

        $tokens = $service->getTokensForNamespace('new-namespace');

        $this->assertSame($service->getDefaults(), $tokens);

        $count = $pdo->query('SELECT COUNT(*) FROM config')->fetchColumn();
        $this->assertSame(0, (int) $count);
    }
}
