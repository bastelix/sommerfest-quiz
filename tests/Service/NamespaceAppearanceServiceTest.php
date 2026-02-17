<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Infrastructure\Database;
use App\Service\ConfigService;
use App\Service\DesignTokenService;
use App\Service\NamespaceDesignFileRepository;
use App\Service\NamespaceAppearanceService;
use App\Service\PageService;
use PDO;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class NamespaceAppearanceServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Database::setFactory(null);
        putenv('DASHBOARD_TOKEN_SECRET');
        unset($_ENV['DASHBOARD_TOKEN_SECRET']);
    }

    public function testLoadThrowsWhenNamespaceConfigIsMissing(): void
    {
        $designTokens = $this->createMock(DesignTokenService::class);
        $designTokens->expects($this->never())->method('getTokensForNamespace');

        $configService = $this->createMock(ConfigService::class);
        $configService->expects($this->once())
            ->method('getConfigForEvent')
            ->with('tenant')
            ->willReturn([]);

        $service = $this->createServiceWithoutConstructor($designTokens, $configService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No design configuration found for namespace: tenant');

        $service->load('tenant');
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
        $this->assertSame('#111111', $appearance['colors']['topbarLight']);
        $this->assertSame('#222222', $appearance['colors']['topbarDark']);
        $this->assertSame('#123456', $appearance['variables']['surface']);
        $this->assertSame('#abcdef', $appearance['variables']['surfaceMuted']);
        $this->assertSame('#111111', $appearance['variables']['topbarLight']);
        $this->assertSame('#222222', $appearance['variables']['topbarDark']);
    }

    public function testLoadUsesFileBasedDesignWhenConfigIsMissing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE namespaces (namespace TEXT PRIMARY KEY, label TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE config (event_uid TEXT PRIMARY KEY, design_tokens TEXT, colors TEXT, backgroundColor TEXT, buttonColor TEXT)');

        $designRoot = sys_get_temp_dir() . '/namespace-design-' . uniqid();
        mkdir($designRoot . '/content/design', 0777, true);

        $designPayload = json_encode([
            'config' => [
                'colors' => [
                    'surface' => '#101010',
                    'muted' => '#202020',
                ],
            ],
            'tokens' => [
                'brand' => ['primary' => '#333333', 'accent' => '#444444'],
            ],
        ], JSON_THROW_ON_ERROR);

        file_put_contents($designRoot . '/content/design/wiki.json', $designPayload);

        Database::setFactory(static fn (): PDO => $pdo);

        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';

        $designFiles = new NamespaceDesignFileRepository($designRoot);
        $configService = new ConfigService($pdo, designFiles: $designFiles);
        $designTokens = new DesignTokenService($pdo, $configService, null, $designFiles);
        $service = new NamespaceAppearanceService($designTokens, $configService);

        $appearance = $service->load('wiki');

        $this->assertSame('#101010', $appearance['colors']['surface']);
        $this->assertSame('#202020', $appearance['colors']['muted']);
        $this->assertSame('#333333', $appearance['colors']['primary']);
        $this->assertSame('#444444', $appearance['colors']['accent']);
    }

    private function createServiceWithoutConstructor(
        DesignTokenService $designTokens,
        ConfigService $configService
    ): NamespaceAppearanceService {
        $reflection = new ReflectionClass(NamespaceAppearanceService::class);

        /** @var NamespaceAppearanceService $service */
        $service = $reflection->newInstanceWithoutConstructor();

        $designTokensProperty = new ReflectionProperty(NamespaceAppearanceService::class, 'designTokens');
        $designTokensProperty->setAccessible(true);
        $designTokensProperty->setValue($service, $designTokens);

        $configServiceProperty = new ReflectionProperty(NamespaceAppearanceService::class, 'configService');
        $configServiceProperty->setAccessible(true);
        $configServiceProperty->setValue($service, $configService);

        return $service;
    }
}
