<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Infrastructure\Database;
use App\Service\ConfigService;
use App\Service\DesignTokenService;
use App\Service\NamespaceAppearanceService;
use App\Service\PageService;
use PDO;
use Tests\TestCase;

class NamespaceAppearanceServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Database::setFactory(null);
    }

    public function testLoadMergesSurfaceAndTopbarColors(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE namespaces (namespace TEXT PRIMARY KEY, label TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE config (event_uid TEXT PRIMARY KEY, design_tokens TEXT, colors TEXT, backgroundColor TEXT, buttonColor TEXT)');

        Database::setFactory(static fn (): PDO => $pdo);

        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';

        $configService = new ConfigService($pdo);
        $designTokens = new DesignTokenService($pdo, $configService);
        $defaults = $designTokens->getDefaults();

        $insert = $pdo->prepare('INSERT INTO config (event_uid, design_tokens, colors, backgroundColor, buttonColor) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([PageService::DEFAULT_NAMESPACE, json_encode($defaults), json_encode([]), '#ffffff', '#1e87f0']);

        $customColors = [
            'surface' => '#123456',
            'muted' => '#abcdef',
            'topbar_light' => '#111111',
            'topbar_dark' => '#222222',
        ];
        $insert->execute(['tenant', json_encode($defaults), json_encode($customColors), '#fedcba', '#1e87f0']);

        $service = new NamespaceAppearanceService($designTokens, $configService);
        $appearance = $service->load('tenant');

        $this->assertSame('#123456', $appearance['colors']['surface']);
        $this->assertSame('#abcdef', $appearance['colors']['muted']);
        $this->assertSame('#111111', $appearance['colors']['topbar_light']);
        $this->assertSame('#222222', $appearance['colors']['topbar_dark']);
        $this->assertSame('#123456', $appearance['variables']['surface']);
        $this->assertSame('#abcdef', $appearance['variables']['surfaceMuted']);
        $this->assertSame('#111111', $appearance['variables']['topbarLight']);
        $this->assertSame('#222222', $appearance['variables']['topbarDark']);
    }
}
