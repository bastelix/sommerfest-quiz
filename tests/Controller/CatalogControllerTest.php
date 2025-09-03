<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\CatalogController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use Tests\TestCase;
use Slim\Psr7\Response;

class CatalogControllerTest extends TestCase
{
    public function testGetNotFound(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                comment TEXT,
                event_uid TEXT
            );
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
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $controller = new CatalogController($service);
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $request = $this->createRequest('GET', '/kataloge/missing.json', ['HTTP_ACCEPT' => 'application/json']);
        $response = $controller->get($request, new Response(), ['file' => 'missing.json']);
        $this->assertEquals(404, $response->getStatusCode());
        session_destroy();
    }

    public function testRedirectIncludesEvent(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                comment TEXT,
                event_uid TEXT
            );
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
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $controller = new CatalogController($service);
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];

        $request = $this->createRequest('GET', '/kataloge/test.json');
        $request = $request->withQueryParams(['event' => 'ev123']);
        $response = $controller->get($request, new Response(), ['file' => 'test.json']);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/?event=ev123&katalog=test', $response->getHeaderLine('Location'));
        session_destroy();
    }

    public function testPostAndGet(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                comment TEXT,
                event_uid TEXT
            );
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
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $controller = new CatalogController($service);
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];

        $request = $this->createRequest('POST', '/kataloge/test.json');
        $request = $request->withParsedBody([[
            'type' => 'swipe',
            'prompt' => 'Q1',
            'cards' => [['text' => 'A', 'correct' => true]],
            'rightLabel' => 'Yes',
            'leftLabel' => 'No',
        ]]);
        $postResponse = $controller->post($request, new Response(), ['file' => 'test.json']);
        $this->assertEquals(204, $postResponse->getStatusCode());

        $getResponse = $controller->get(
            $this->createRequest('GET', '/kataloge/test.json', ['HTTP_ACCEPT' => 'application/json']),
            new Response(),
            ['file' => 'test.json']
        );
        $this->assertEquals(200, $getResponse->getStatusCode());
        $expected = json_encode([[
            'type' => 'swipe',
            'prompt' => 'Q1',
            'cards' => [['text' => 'A', 'correct' => true]],
            'rightLabel' => 'Yes',
            'leftLabel' => 'No',
        ]], JSON_PRETTY_PRINT);
        $this->assertJsonStringEqualsJsonString($expected, (string) $getResponse->getBody());

        session_destroy();
    }

    public function testCreateAndDelete(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                comment TEXT,
                event_uid TEXT
            );
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
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $controller = new CatalogController($service);
        session_start();
        $_SESSION["user"] = ["id" => 1, "role" => "catalog-editor"];

        $createReq = $this->createRequest('POST', '/kataloge/new.json');
        $createReq = $createReq->withParsedBody([]);
        $createRes = $controller->post($createReq, new Response(), ['file' => 'new.json']);
        $this->assertEquals(204, $createRes->getStatusCode());
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM catalogs WHERE file=? AND slug=?');
        $stmt->execute(['new.json', 'new']);
        $this->assertSame(1, (int) $stmt->fetchColumn());

        $getRes = $controller->get(
            $this->createRequest('GET', '/kataloge/new.json', ['HTTP_ACCEPT' => 'application/json']),
            new Response(),
            ['file' => 'new.json']
        );
        $this->assertEquals(200, $getRes->getStatusCode());

        $deleteRes = $controller->delete(
            $this->createRequest('DELETE', '/kataloge/new.json'),
            new Response(),
            ['file' => 'new.json']
        );
        $this->assertEquals(204, $deleteRes->getStatusCode());
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM catalogs WHERE file=? AND slug=?');
        $stmt->execute(['new.json', 'new']);
        $this->assertSame(0, (int) $stmt->fetchColumn());

        $this->assertSame(404, $controller->get(
            $this->createRequest('GET', '/kataloge/new.json', ['HTTP_ACCEPT' => 'application/json']),
            new Response(),
            ['file' => 'new.json']
        )->getStatusCode());

        session_destroy();
    }

    public function testDeleteQuestion(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                comment TEXT,
                event_uid TEXT
            );
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
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $controller = new CatalogController($service);
        session_start();
        $_SESSION["user"] = ["id" => 1, "role" => "catalog-editor"];

        $service->write('cat.json', [
            ['type' => 'text', 'prompt' => 'A'],
            ['type' => 'text', 'prompt' => 'B'],
        ]);

        $req = $this->createRequest('DELETE', '/kataloge/cat.json/0');
        $res = $controller->deleteQuestion($req, new Response(), ['file' => 'cat.json', 'index' => '0']);
        $this->assertEquals(204, $res->getStatusCode());

        $data = json_decode($service->read('cat.json'), true);
        $this->assertCount(1, $data);
        $this->assertSame('B', $data[0]['prompt']);

        session_destroy();
    }

    public function testPostInvalidJson(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                comment TEXT,
                event_uid TEXT
            );
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
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $cfg = new ConfigService($pdo);
        $controller = new CatalogController(new CatalogService($pdo, $cfg));
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];

        $request = $this->createRequest('POST', '/kataloge/test.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '{invalid');
        rewind($stream);
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream);
        $request = $request->withBody($stream);

        $response = $controller->post($request, new Response(), ['file' => 'test.json']);
        $this->assertEquals(400, $response->getStatusCode());

        session_destroy();
    }
}
