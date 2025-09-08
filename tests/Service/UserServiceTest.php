<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\UserService;
use App\Domain\Roles;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    public function testSaveAllStoresPositionAndReturnsOrdered(): void
    {
        $pdo = $this->createDatabase();
        $svc = new UserService($pdo);

        $svc->saveAll([
            ['username' => 'alice', 'role' => Roles::ADMIN],
            ['username' => 'bob', 'role' => Roles::CATALOG_EDITOR],
            ['username' => 'carol', 'role' => Roles::ANALYST],
        ]);

        $users = $svc->getAll();
        $this->assertSame(['alice', 'bob', 'carol'], array_column($users, 'username'));
        $this->assertSame([0, 1, 2], array_column($users, 'position'));

        $svc->saveAll([
            ['id' => $users[2]['id'], 'username' => 'carol', 'role' => $users[2]['role'], 'active' => true],
            ['id' => $users[0]['id'], 'username' => 'alice', 'role' => $users[0]['role'], 'active' => true],
            ['id' => $users[1]['id'], 'username' => 'bob', 'role' => $users[1]['role'], 'active' => true],
        ]);

        $users = $svc->getAll();
        $this->assertSame(['carol', 'alice', 'bob'], array_column($users, 'username'));
        $this->assertSame([0, 1, 2], array_column($users, 'position'));
    }
}
