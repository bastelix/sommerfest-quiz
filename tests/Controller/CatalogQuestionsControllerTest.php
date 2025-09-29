<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\CatalogController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use Tests\TestCase;
use Slim\Psr7\Response;

class CatalogQuestionsControllerTest extends TestCase
{
    public function testGetQuestionsRequiresValidTokenAndWhitelist(): void {
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
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name) VALUES('u1',1,'station_1','station_1.json','Station 1');");
        $pdo->exec("INSERT INTO questions(catalog_uid,sort_order,type,prompt) VALUES('u1',1,'text','Frage?');");

        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $controller = new CatalogController($service);

        session_start();
        $_SESSION['csrf_token'] = 'token';
        $_SESSION['catalog_files'] = ['station_1.json'];

        $req = $this->createRequest('GET', '/catalog/questions/station_1.json', [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'token',
        ]);
        $res = $controller->getQuestions($req, new Response(), ['file' => 'station_1.json']);
        $this->assertSame(200, $res->getStatusCode());
        $data = json_decode((string) $res->getBody(), true);
        $this->assertIsArray($data);
        $this->assertSame('Frage?', $data[0]['prompt'] ?? null);

        $req = $this->createRequest('GET', '/catalog/questions/station_1.json', [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $res = $controller->getQuestions($req, new Response(), ['file' => 'station_1.json']);
        $this->assertSame(403, $res->getStatusCode());
    }
}
