<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ConfigService;
use App\Service\DesignTokenService;
use App\Service\NamespaceDesignFileRepository;
use InvalidArgumentException;
use PDO;
use Tests\TestCase;

class DesignImportTest extends TestCase
{
    private string $designRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->designRoot = sys_get_temp_dir() . '/design-import-test-' . uniqid();
        mkdir($this->designRoot . '/content/design', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->designRoot);
        parent::tearDown();
    }

    public function testImportDesignAppliesTokensAndColors(): void
    {
        $pdo = $this->createSqliteDatabase();
        $preset = [
            'meta' => [
                'name' => 'Test Theme',
                'description' => 'A test theme preset',
            ],
            'config' => [
                'designTokens' => [
                    'brand' => [
                        'primary' => '#138f52',
                        'accent' => '#9cd78f',
                        'secondary' => '#123524',
                    ],
                    'layout' => ['profile' => 'standard'],
                    'typography' => ['preset' => 'modern'],
                    'components' => [
                        'cardStyle' => 'rounded',
                        'buttonStyle' => 'filled',
                    ],
                ],
                'colors' => [
                    'textOnPrimary' => '#ffffff',
                    'textOnSurface' => '#123524',
                    'textOnBackground' => '#123524',
                ],
            ],
            'designTokens' => [
                'brand' => [
                    'primary' => '#138f52',
                    'accent' => '#9cd78f',
                    'secondary' => '#123524',
                ],
                'layout' => ['profile' => 'standard'],
                'typography' => ['preset' => 'modern'],
                'components' => [
                    'cardStyle' => 'rounded',
                    'buttonStyle' => 'filled',
                ],
            ],
        ];

        file_put_contents(
            $this->designRoot . '/content/design/test-theme.json',
            json_encode($preset, JSON_THROW_ON_ERROR)
        );

        $designFiles = new NamespaceDesignFileRepository($this->designRoot);
        $configService = new ConfigService($pdo, designFiles: $designFiles);
        $cssPath = tempnam(sys_get_temp_dir(), 'namespace-tokens-');
        $service = new DesignTokenService($pdo, $configService, $cssPath, $designFiles);

        $result = $service->importDesign('my-namespace', 'test-theme');

        $this->assertSame('#138f52', $result['tokens']['brand']['primary']);
        $this->assertSame('#9cd78f', $result['tokens']['brand']['accent']);
        $this->assertSame('#123524', $result['tokens']['brand']['secondary']);
        $this->assertSame('standard', $result['tokens']['layout']['profile']);
        $this->assertSame('modern', $result['tokens']['typography']['preset']);
        $this->assertSame('#ffffff', $result['colors']['textOnPrimary']);
        $this->assertSame('#123524', $result['colors']['textOnSurface']);

        $storedTokens = $service->getTokensForNamespace('my-namespace');
        $this->assertSame('#138f52', $storedTokens['brand']['primary']);

        $storedConfig = $configService->getConfigForEvent('my-namespace');
        $this->assertIsArray($storedConfig['colors'] ?? null);
        $this->assertSame('#ffffff', $storedConfig['colors']['textOnPrimary']);
    }

    public function testImportDesignWithEffects(): void
    {
        $pdo = $this->createSqliteDatabase();
        $preset = [
            'designTokens' => [
                'brand' => ['primary' => '#222222'],
            ],
            'effects' => [
                'effectsProfile' => 'quizrace.calm',
                'sliderProfile' => 'calm',
            ],
        ];

        file_put_contents(
            $this->designRoot . '/content/design/effects-preset.json',
            json_encode($preset, JSON_THROW_ON_ERROR)
        );

        $designFiles = new NamespaceDesignFileRepository($this->designRoot);
        $configService = new ConfigService($pdo, designFiles: $designFiles);
        $cssPath = tempnam(sys_get_temp_dir(), 'namespace-tokens-');
        $service = new DesignTokenService($pdo, $configService, $cssPath, $designFiles);

        $result = $service->importDesign('effect-ns', 'effects-preset');

        $this->assertSame('quizrace.calm', $result['effects']['effectsProfile']);
        $this->assertSame('calm', $result['effects']['sliderProfile']);
    }

    public function testImportDesignThrowsForMissingPreset(): void
    {
        $pdo = $this->createSqliteDatabase();
        $designFiles = new NamespaceDesignFileRepository($this->designRoot);
        $configService = new ConfigService($pdo, designFiles: $designFiles);
        $cssPath = tempnam(sys_get_temp_dir(), 'namespace-tokens-');
        $service = new DesignTokenService($pdo, $configService, $cssPath, $designFiles);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('design-preset-not-found');

        $service->importDesign('my-namespace', 'nonexistent-preset');
    }

    public function testListAvailablePresets(): void
    {
        $pdo = $this->createSqliteDatabase();

        $presetA = [
            'meta' => ['name' => 'Alpha Theme', 'description' => 'First theme'],
            'designTokens' => ['brand' => ['primary' => '#111111']],
        ];
        $presetB = [
            'meta' => ['name' => 'Beta Theme'],
            'designTokens' => ['brand' => ['primary' => '#222222']],
        ];

        file_put_contents(
            $this->designRoot . '/content/design/alpha.json',
            json_encode($presetA, JSON_THROW_ON_ERROR)
        );
        file_put_contents(
            $this->designRoot . '/content/design/beta.json',
            json_encode($presetB, JSON_THROW_ON_ERROR)
        );

        $designFiles = new NamespaceDesignFileRepository($this->designRoot);
        $configService = new ConfigService($pdo, designFiles: $designFiles);
        $cssPath = tempnam(sys_get_temp_dir(), 'namespace-tokens-');
        $service = new DesignTokenService($pdo, $configService, $cssPath, $designFiles);

        $presets = $service->listAvailablePresets();

        $this->assertCount(2, $presets);

        $names = array_column($presets, 'name');
        $this->assertContains('alpha', $names);
        $this->assertContains('beta', $names);

        $alphaPreset = array_values(array_filter($presets, fn ($p) => $p['name'] === 'alpha'))[0];
        $this->assertSame('Alpha Theme', $alphaPreset['label']);
        $this->assertSame('First theme', $alphaPreset['description']);
    }

    public function testLoadFileIsPubliclyAccessible(): void
    {
        $preset = [
            'meta' => ['name' => 'Public Test'],
            'designTokens' => ['brand' => ['primary' => '#aabbcc']],
        ];

        file_put_contents(
            $this->designRoot . '/content/design/public-test.json',
            json_encode($preset, JSON_THROW_ON_ERROR)
        );

        $designFiles = new NamespaceDesignFileRepository($this->designRoot);
        $data = $designFiles->loadFile('public-test');

        $this->assertArrayHasKey('meta', $data);
        $this->assertSame('Public Test', $data['meta']['name']);
    }

    public function testFutureIsGreenPresetExists(): void
    {
        $designFiles = new NamespaceDesignFileRepository(dirname(__DIR__, 2));
        $data = $designFiles->loadFile('future-is-green');

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('config', $data);
        $this->assertArrayHasKey('designTokens', $data);
        $this->assertArrayHasKey('contrastPairs', $data);
        $this->assertArrayHasKey('meta', $data);

        $tokens = $data['designTokens'];
        $this->assertSame('#138f52', $tokens['brand']['primary']);
        $this->assertSame('#9cd78f', $tokens['brand']['accent']);
        $this->assertSame('#123524', $tokens['brand']['secondary']);
    }

    private function createSqliteDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY, design_tokens TEXT, colors TEXT, effects_profile TEXT, slider_profile TEXT)');
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

        return $pdo;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
