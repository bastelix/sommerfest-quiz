<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CatalogService;
use PDO;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalogs(uid TEXT PRIMARY KEY, sort_order INTEGER UNIQUE NOT NULL, slug TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT, comment TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_uid TEXT NOT NULL, sort_order INTEGER UNIQUE, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        return $pdo;
    }

    private function createPdoNoComment(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalogs(uid TEXT PRIMARY KEY, sort_order INTEGER UNIQUE NOT NULL, slug TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_uid TEXT NOT NULL, sort_order INTEGER UNIQUE, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
        return $pdo;
    }

    public function testReadWrite(): void
    {
        $pdo = $this->createPdo();
        $service = new CatalogService($pdo);
        $file = 'test.json';
        $catalog = [[
            'uid' => 'uid1',
            'id' => 'cat1',
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
        $service = new CatalogService($pdo);
        $catalog = [[
            'uid' => 'uid4',
            'id' => 'nc',
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
        $service = new CatalogService($pdo);

        $this->assertNull($service->read('missing.json'));
    }

    public function testDelete(): void
    {
        $pdo = $this->createPdo();
        $service = new CatalogService($pdo);
        $file = 'del.json';
        $service->write('catalogs.json', [[
            'uid' => 'uid2',
            'id' => 'del',
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
        $service = new CatalogService($pdo);
        $file = 'q.json';
        $service->write('catalogs.json', [[
            'uid' => 'uid3',
            'id' => 'qid',
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
        $service = new CatalogService($pdo);
        $initial = [
            ['uid' => 'u1', 'id' => 1, 'slug' => 'a', 'file' => 'a.json', 'name' => 'A', 'comment' => ''],
            ['uid' => 'u2', 'id' => 2, 'slug' => 'b', 'file' => 'b.json', 'name' => 'B', 'comment' => ''],
        ];
        $service->write('catalogs.json', $initial);

        $reordered = [
            ['uid' => 'u1', 'id' => 2, 'slug' => 'a', 'file' => 'a.json', 'name' => 'A', 'comment' => ''],
            ['uid' => 'u2', 'id' => 1, 'slug' => 'b', 'file' => 'b.json', 'name' => 'B', 'comment' => ''],
        ];
        $service->write('catalogs.json', $reordered);
        $list = json_decode($service->read('catalogs.json'), true);
        $this->assertSame('b', $list[0]['slug']);
        $this->assertSame('a', $list[1]['slug']);
    }

    public function testNamesRemainAfterReorder(): void
    {
        $pdo = $this->createPdo();
        $service = new CatalogService($pdo);
        $initial = [
            ['uid' => 'u1', 'id' => 1, 'slug' => 'a', 'file' => 'a.json', 'name' => 'One', 'comment' => ''],
            ['uid' => 'u2', 'id' => 2, 'slug' => 'b', 'file' => 'b.json', 'name' => 'Two', 'comment' => ''],
            ['uid' => 'u3', 'id' => 3, 'slug' => 'c', 'file' => 'c.json', 'name' => 'Three', 'comment' => ''],
        ];
        $service->write('catalogs.json', $initial);

        $reordered = [
            ['uid' => 'u2', 'id' => 1, 'slug' => 'b', 'file' => 'b.json', 'name' => 'Two', 'comment' => ''],
            ['uid' => 'u1', 'id' => 2, 'slug' => 'a', 'file' => 'a.json', 'name' => 'One', 'comment' => ''],
            ['uid' => 'u3', 'id' => 3, 'slug' => 'c', 'file' => 'c.json', 'name' => 'Three', 'comment' => ''],
        ];
        $service->write('catalogs.json', $reordered);
        $rows = json_decode($service->read('catalogs.json'), true);
        $this->assertSame(['Two', 'One', 'Three'], array_column($rows, 'name'));
    }
}
