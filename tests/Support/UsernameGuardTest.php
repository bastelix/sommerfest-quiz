<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\UsernameBlockedException;
use App\Support\UsernameGuard;
use PDO;
use PHPUnit\Framework\TestCase;

class UsernameGuardTest extends TestCase
{
    public function testDatabaseEntriesAreBlocked(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(<<<'SQL'
            CREATE TABLE username_blocklist (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                term TEXT NOT NULL,
                category TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
        $pdo->exec(<<<'SQL'
            INSERT INTO username_blocklist (term, category)
            VALUES ('verboten', 'NSFW')
        SQL);

        $guard = UsernameGuard::fromConfigFile(null, $pdo);

        $this->expectException(UsernameBlockedException::class);
        $guard->assertAllowed('verboten');
    }
}
