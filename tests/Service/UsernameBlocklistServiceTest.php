<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Exception\DuplicateUsernameBlocklistException;
use App\Service\UsernameBlocklistService;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class UsernameBlocklistServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE username_blocklist (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                term TEXT NOT NULL,
                category TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT username_blocklist_category_check
                    CHECK (category IN ('NSFW', '§86a/NS-Bezug', 'Beleidigung/Slur', 'Allgemein', 'Admin'))
            )
        SQL);
    }

    public function testAddNormalizesAndReturnsEntry(): void
    {
        $service = new UsernameBlocklistService($this->pdo);

        $entry = $service->add('  AdminUser  ');

        self::assertSame('adminuser', $entry['term']);
        self::assertSame(UsernameBlocklistService::ADMIN_CATEGORY, $entry['category']);
        self::assertInstanceOf(DateTimeImmutable::class, $entry['created_at']);

        $all = $service->getAdminEntries();
        self::assertCount(1, $all);
        self::assertSame('adminuser', $all[0]['term']);
    }

    public function testAddRejectsDuplicates(): void
    {
        $service = new UsernameBlocklistService($this->pdo);
        $service->add('gesperrt');

        $this->expectException(DuplicateUsernameBlocklistException::class);
        $service->add('GESPERRT');
    }

    public function testAddRejectsShortEntries(): void
    {
        $service = new UsernameBlocklistService($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        $service->add('ab');
    }

    public function testRemoveDeletesEntry(): void
    {
        $service = new UsernameBlocklistService($this->pdo);
        $entry = $service->add('blocked');

        $removed = $service->remove($entry['id']);
        self::assertNotNull($removed);
        self::assertSame('blocked', $removed['term']);

        self::assertSame([], $service->getAdminEntries());
    }

    public function testRemoveIgnoresUnknownId(): void
    {
        $service = new UsernameBlocklistService($this->pdo);
        $result = $service->remove(123);

        self::assertNull($result);
    }
}
