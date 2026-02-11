<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Repository\NamespaceRepository;
use App\Exception\NamespaceInUseException;
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

    public function testAllSkipsNamespacesWithoutPersistedRows(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $service = new NamespaceService($repository);

        $pdo->prepare('INSERT INTO namespace_profile (namespace) VALUES (?)')->execute(['shadow-tenant']);

        $namespaces = $service->all();
        $names = array_column($namespaces, 'namespace');

        $this->assertNotContains('shadow-tenant', $names);
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

    public function testDeleteFailsWhenConfigExists(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $service = new NamespaceService($repository);
        $namespace = 'tenant-x';
        $repository->create($namespace);

        $configService = new ConfigService($pdo);
        $configService->ensureConfigForEvent($namespace);

        $this->expectException(NamespaceInUseException::class);
        $service->delete($namespace);
    }

    public function testDeleteSucceedsAfterRemovingConfig(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $namespace = 'tenant-y';
        $repository->create($namespace);

        $configService = new ConfigService($pdo);
        $configService->ensureConfigForEvent($namespace);
        $pdo->prepare('DELETE FROM config WHERE event_uid = ?')->execute([$namespace]);

        $service = new NamespaceService($repository);
        $service->delete($namespace);

        $entry = $repository->find($namespace);
        $this->assertNotNull($entry);
        $this->assertFalse($entry['is_active']);
    }

    public function testDeleteIgnoresUserNamespaceAssignments(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $namespace = 'temporary';
        $repository->create($namespace);

        $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)')->execute(['tester', 'secret']);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO user_namespaces (user_id, namespace, is_default) VALUES (?, ?, FALSE)')
            ->execute([$userId, $namespace]);

        $service = new NamespaceService($repository);
        $service->delete($namespace);

        $entry = $repository->find($namespace);
        $this->assertNotNull($entry);
        $this->assertFalse($entry['is_active']);
    }

    public function testAllActiveExcludesInactiveNamespaces(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $service = new NamespaceService($repository);

        $repository->create('active-project');
        $repository->create('inactive-project');
        $repository->deactivate('inactive-project');

        $namespaces = $service->allActive();
        $names = array_column($namespaces, 'namespace');

        $this->assertContains('active-project', $names);
        $this->assertNotContains('inactive-project', $names);
    }

    public function testAllActiveAlwaysIncludesDefaultNamespace(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $service = new NamespaceService($repository);

        $namespaces = $service->allActive();
        $names = array_column($namespaces, 'namespace');

        $this->assertContains('default', $names);
    }

    public function testAllStillReturnsInactiveNamespaces(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $service = new NamespaceService($repository);

        $repository->create('will-deactivate');
        $repository->deactivate('will-deactivate');

        $namespaces = $service->all();
        $names = array_column($namespaces, 'namespace');

        $this->assertContains('will-deactivate', $names);
    }
}
