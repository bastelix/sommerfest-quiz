<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Repository\NamespaceRepository;
use App\Service\ConfigService;
use App\Service\DesignTokenService;
use App\Service\NamespaceService;
use Tests\TestCase;

class NamespaceServiceTest extends TestCase
{
    public function testAllReturnsDefaultWhenNamespaceTableIsMissing(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec('DROP TABLE namespaces');

        $service = new NamespaceService(new NamespaceRepository($pdo));

        $namespaces = $service->all();

        $default = array_values(array_filter(
            $namespaces,
            static fn (array $entry): bool => $entry['namespace'] === 'default'
        ));

        $this->assertNotEmpty($default);
        $this->assertTrue($default[0]['is_active']);
    }

    public function testCreatePersistsDesignTokenConfig(): void
    {
        $pdo = $this->createDatabase();
        $configService = new ConfigService($pdo);
        $designTokenService = new DesignTokenService($pdo, $configService);
        $service = new NamespaceService(new NamespaceRepository($pdo), null, $designTokenService);

        $result = $service->create('New-Project');
        $config = $configService->getConfigForEvent($result['namespace']);

        $this->assertSame($result['namespace'], $config['event_uid']);
        $this->assertSame($designTokenService->getDefaults(), $config['designTokens']);
    }
}
