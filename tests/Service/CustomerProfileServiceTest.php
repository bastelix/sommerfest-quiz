<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CustomerProfileService;
use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class CustomerProfileServiceTest extends TestCase
{
    public function testUpsertAndRetrieveProfile(): void
    {
        $pdo = $this->createDatabase();
        $service = new CustomerProfileService($pdo);

        $profile = $service->upsert(1, 'Max Mustermann', 'ACME GmbH', '+49123456', null);

        $this->assertSame(1, $profile->getUserId());
        $this->assertSame('Max Mustermann', $profile->getDisplayName());
        $this->assertSame('ACME GmbH', $profile->getCompany());
        $this->assertSame('+49123456', $profile->getPhone());
        $this->assertNull($profile->getAvatarUrl());

        $fetched = $service->getByUserId(1);
        $this->assertNotNull($fetched);
        $this->assertSame('Max Mustermann', $fetched->getDisplayName());
    }

    public function testUpdateExistingProfile(): void
    {
        $pdo = $this->createDatabase();
        $service = new CustomerProfileService($pdo);

        $service->upsert(1, 'Original Name', null, null, null);
        $updated = $service->upsert(1, 'New Name', 'New Corp', '+49999', 'https://example.com/avatar.png');

        $this->assertSame('New Name', $updated->getDisplayName());
        $this->assertSame('New Corp', $updated->getCompany());
        $this->assertSame('+49999', $updated->getPhone());
        $this->assertSame('https://example.com/avatar.png', $updated->getAvatarUrl());
    }

    public function testDeleteProfile(): void
    {
        $pdo = $this->createDatabase();
        $service = new CustomerProfileService($pdo);

        $service->upsert(1, 'To delete', null, null, null);
        $service->delete(1);

        $this->assertNull($service->getByUserId(1));
    }

    public function testGetByUserIdReturnsNullForUnknownUser(): void
    {
        $pdo = $this->createDatabase();
        $service = new CustomerProfileService($pdo);

        $this->assertNull($service->getByUserId(999));
    }

    private function createDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE customer_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            display_name TEXT NULL,
            company TEXT NULL,
            phone TEXT NULL,
            avatar_url TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        return $pdo;
    }
}
