<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Repository\NamespaceRepository;
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
}
