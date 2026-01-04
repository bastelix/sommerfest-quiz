<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Repository\NamespaceRepository;
use App\Exception\NamespaceInUseException;
use App\Service\ConfigService;
use App\Service\DesignTokenService;
use App\Service\NamespaceService;
use PDO;
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

    public function testAllReturnsPersistedNamespacesOnly(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $service = new NamespaceService($repository);

        $userId = $this->createUser($pdo, 'user-all-namespaces');
        $pdo->prepare('INSERT INTO user_namespaces (user_id, namespace, is_default) VALUES (?, ?, FALSE)')
            ->execute([$userId, 'ephemeral']);

        $namespaces = $service->all();

        $this->assertSame([], array_values(array_filter(
            $namespaces,
            static fn (array $entry): bool => $entry['namespace'] === 'ephemeral'
        )));
    }

    public function testDeleteIgnoresUserNamespaceAssignments(): void
    {
        $pdo = $this->createDatabase();
        $repository = new NamespaceRepository($pdo);
        $namespace = 'temp-usage';
        $repository->create($namespace);

        $userId = $this->createUser($pdo, 'user-temp-usage');
        $pdo->prepare('INSERT INTO user_namespaces (user_id, namespace, is_default) VALUES (?, ?, FALSE)')
            ->execute([$userId, $namespace]);

        $service = new NamespaceService($repository);
        $service->delete($namespace);

        $entry = $repository->find($namespace);
        $this->assertNotNull($entry);
        $this->assertFalse($entry['is_active']);
    }

    private function createUser(PDO $pdo, string $username): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password, role, active) VALUES (?, ?, ?, TRUE) RETURNING id'
        );
        $stmt->execute([$username, 'secret', 'catalog-editor']);
        $id = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $id;
    }
}
