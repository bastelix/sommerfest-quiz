<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CatalogService;
use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    private function createPdo(): PDO
    {
        return $this->createMigratedPdo();
    }

    private function createPdoNoComment(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                qrcode_url TEXT,
                raetsel_buchstabe TEXT,
                design_path TEXT,
                event_uid TEXT
            );
            CREATE UNIQUE INDEX catalogs_unique_sort_order ON catalogs(event_uid, sort_order);
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE questions(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalog_uid TEXT NOT NULL,
                sort_order INTEGER,
                type TEXT NOT NULL,
                prompt TEXT NOT NULL,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        return $pdo;
    }

    public function testReadWrite(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $file = 'test.json';
        $catalog = [[
            'uid' => 'uid1',
            'sort_order' => 'cat1',
            'slug' => 'cat1',
            'file' => $file,
            'name' => 'Test',
            'comment' => ''
        ]];
        $service->write('catalogs.json', $catalog);
        $data = [['type' => 'text', 'prompt' => 'Hello']];

        $service->write($file, $data);
        $this->assertJsonStringEqualsJsonString(json_encode($data, JSON_PRETTY_PRINT), $service->read($file));
    }

    public function testWriteWithoutCommentColumn(): void
    {
        $pdo = $this->createPdoNoComment();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $catalog = [[
            'uid' => 'uid4',
            'sort_order' => 'nc',
            'slug' => 'nc',
            'file' => 'nc.json',
            'name' => 'NC',
            'comment' => 'ignored',
        ]];
        $service->write('catalogs.json', $catalog);
        $rows = json_decode($service->read('catalogs.json'), true);
        $this->assertSame('ignored', $rows[0]['comment']);
    }

    public function testReadReturnsNullIfMissing(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);

        $this->assertNull($service->read('missing.json'));
    }

    public function testDelete(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $file = 'del.json';
        $service->write('catalogs.json', [[
            'uid' => 'uid2',
            'sort_order' => 'del',
            'slug' => 'del',
            'file' => $file,
            'name' => 'Del',
            'comment' => ''
        ]]);
        $service->write($file, []);
        $stmt = $pdo->query('SELECT COUNT(*) FROM questions');
        $this->assertSame('0', $stmt->fetchColumn());
        $service->delete($file);
        $stmt = $pdo->query('SELECT COUNT(*) FROM catalogs');
        $this->assertSame('0', $stmt->fetchColumn());
    }

    public function testDeleteQuestion(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $file = 'q.json';
        $service->write('catalogs.json', [[
            'uid' => 'uid3',
            'sort_order' => 'qid',
            'slug' => 'qid',
            'file' => $file,
            'name' => 'Q',
            'comment' => ''
        ]]);
        $data = [
            ['type' => 'text', 'prompt' => 'A'],
            ['type' => 'text', 'prompt' => 'B'],
        ];
        $service->write($file, $data);

        $service->deleteQuestion($file, 0);
        $remaining = json_decode($service->read($file), true);
        $this->assertCount(1, $remaining);
        $this->assertSame('B', $remaining[0]['prompt']);
    }

    public function testReorderCatalogs(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $initial = [
            ['uid' => 'u1', 'sort_order' => 1, 'slug' => 'a', 'file' => 'a.json', 'name' => 'A', 'comment' => ''],
            ['uid' => 'u2', 'sort_order' => 2, 'slug' => 'b', 'file' => 'b.json', 'name' => 'B', 'comment' => ''],
        ];
        $service->write('catalogs.json', $initial);

        $reordered = [
            ['uid' => 'u1', 'sort_order' => 2, 'slug' => 'a', 'file' => 'a.json', 'name' => 'A', 'comment' => ''],
            ['uid' => 'u2', 'sort_order' => 1, 'slug' => 'b', 'file' => 'b.json', 'name' => 'B', 'comment' => ''],
        ];
        $service->write('catalogs.json', $reordered);
        $list = json_decode($service->read('catalogs.json'), true);
        $this->assertSame('b', $list[0]['slug']);
        $this->assertSame('a', $list[1]['slug']);
    }

    public function testNamesRemainAfterReorder(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $initial = [
            ['uid' => 'u1', 'sort_order' => 1, 'slug' => 'a', 'file' => 'a.json', 'name' => 'One', 'comment' => ''],
            ['uid' => 'u2', 'sort_order' => 2, 'slug' => 'b', 'file' => 'b.json', 'name' => 'Two', 'comment' => ''],
            ['uid' => 'u3', 'sort_order' => 3, 'slug' => 'c', 'file' => 'c.json', 'name' => 'Three', 'comment' => ''],
        ];
        $service->write('catalogs.json', $initial);

        $reordered = [
            ['uid' => 'u2', 'sort_order' => 1, 'slug' => 'b', 'file' => 'b.json', 'name' => 'Two', 'comment' => ''],
            ['uid' => 'u1', 'sort_order' => 2, 'slug' => 'a', 'file' => 'a.json', 'name' => 'One', 'comment' => ''],
            ['uid' => 'u3', 'sort_order' => 3, 'slug' => 'c', 'file' => 'c.json', 'name' => 'Three', 'comment' => ''],
        ];
        $service->write('catalogs.json', $reordered);
        $rows = json_decode($service->read('catalogs.json'), true);
        $this->assertSame(['Two', 'One', 'Three'], array_column($rows, 'name'));
    }

    public function testWriteWithoutActiveEventUid(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO config(event_uid) VALUES(NULL)");
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $catalog = [[
            'uid' => 'uid5',
            'sort_order' => 1,
            'slug' => 'ne',
            'file' => 'ne.json',
            'name' => 'NoEvent',
            'comment' => ''
        ]];
        $service->write('catalogs.json', $catalog);
        $stmt = $pdo->query('SELECT event_uid FROM catalogs');
        $this->assertNull($stmt->fetchColumn());
    }
}
