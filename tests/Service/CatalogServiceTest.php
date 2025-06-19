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
        $pdo->exec('CREATE TABLE catalogs(uid TEXT PRIMARY KEY, id TEXT UNIQUE NOT NULL, file TEXT NOT NULL, name TEXT NOT NULL, description TEXT, qrcode_url TEXT, raetsel_buchstabe TEXT, comment TEXT);');
        $pdo->exec('CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_id TEXT NOT NULL, type TEXT NOT NULL, prompt TEXT NOT NULL, options TEXT, answers TEXT, terms TEXT, items TEXT);');
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
            'file' => $file,
            'name' => 'Test',
            'comment' => ''
        ]];
        $service->write('catalogs.json', $catalog);
        $data = [['type' => 'text', 'prompt' => 'Hello']];

        $service->write($file, $data);
        $this->assertJsonStringEqualsJsonString(json_encode($data, JSON_PRETTY_PRINT), $service->read($file));
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
}
