<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Exception\DuplicateUsernameBlocklistException;
use App\Service\UsernameBlocklistService;
use App\Support\UsernameGuard;
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
        $this->pdo->exec('CREATE UNIQUE INDEX idx_username_blocklist_term_category ON username_blocklist (LOWER(term), category)');
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

    public function testImportEntriesPersistsEveryCategory(): void
    {
        $service = new UsernameBlocklistService($this->pdo);

        $rows = [];
        foreach (UsernameGuard::DATABASE_CATEGORIES as $index => $category) {
            $rows[] = [
                'term' => sprintf('  ExampleTerm%d  ', $index),
                'category' => $category,
            ];
        }

        $service->importEntries($rows);

        $stmt = $this->pdo->query('SELECT term, category FROM username_blocklist ORDER BY category');
        $stored = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        self::assertCount(count(UsernameGuard::DATABASE_CATEGORIES), $stored);

        $expected = [];
        foreach (UsernameGuard::DATABASE_CATEGORIES as $index => $category) {
            $expected[$category] = mb_strtolower(trim(sprintf('  ExampleTerm%d  ', $index)));
        }

        $actual = [];
        foreach ($stored as $row) {
            $actual[$row['category']] = $row['term'];
        }

        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    public function testImportEntriesDeduplicatesPerCategory(): void
    {
        $service = new UsernameBlocklistService($this->pdo);

        $service->importEntries([
            ['term' => 'Alpha', 'category' => UsernameBlocklistService::ADMIN_CATEGORY],
            ['term' => 'alpha', 'category' => UsernameBlocklistService::ADMIN_CATEGORY],
            ['term' => 'Beta', 'category' => UsernameBlocklistService::ADMIN_CATEGORY],
            ['term' => 'Alpha', 'category' => 'NSFW'],
        ]);

        $service->importEntries([
            ['term' => 'ALPHA', 'category' => UsernameBlocklistService::ADMIN_CATEGORY],
            ['term' => 'gamma', 'category' => 'admin'],
        ]);

        $stmtAdmin = $this->pdo->query('SELECT term, category FROM username_blocklist WHERE category = "Admin" ORDER BY term');
        $adminRows = $stmtAdmin !== false ? $stmtAdmin->fetchAll(PDO::FETCH_ASSOC) : [];

        self::assertSame([
            ['term' => 'alpha', 'category' => 'Admin'],
            ['term' => 'beta', 'category' => 'Admin'],
            ['term' => 'gamma', 'category' => 'Admin'],
        ], $adminRows);

        $stmtNsfw = $this->pdo->query('SELECT term, category FROM username_blocklist WHERE category = "NSFW"');
        $nsfwRows = $stmtNsfw !== false ? $stmtNsfw->fetchAll(PDO::FETCH_ASSOC) : [];

        self::assertSame([
            ['term' => 'alpha', 'category' => 'NSFW'],
        ], $nsfwRows);
    }

    public function testImportEntriesRejectsUnknownCategory(): void
    {
        $service = new UsernameBlocklistService($this->pdo);

        $this->expectException(InvalidArgumentException::class);

        $service->importEntries([
            ['term' => 'validterm', 'category' => 'Unknown'],
        ]);
    }

    public function testImportEntriesRejectsShortTerms(): void
    {
        $service = new UsernameBlocklistService($this->pdo);

        $this->expectException(InvalidArgumentException::class);

        $service->importEntries([
            ['term' => 'ab', 'category' => UsernameBlocklistService::ADMIN_CATEGORY],
        ]);
    }
}
