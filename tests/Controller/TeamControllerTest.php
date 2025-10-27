<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\TeamController;
use App\Service\ConfigService;
use App\Service\TeamService;
use App\Service\ResultService;
use App\Support\TokenCipher;
use App\Service\TenantService;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    public function testPostExceedingTeamLimitReturns402(): void {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE tenants(uid TEXT PRIMARY KEY, subdomain TEXT UNIQUE, plan TEXT, custom_limits TEXT)');
        $pdo->exec('CREATE TABLE teams(uid TEXT PRIMARY KEY, event_uid TEXT, sort_order INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event1')");
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan, custom_limits) VALUES('t1','sub1','starter', NULL)");
        $cfg = new ConfigService($pdo, new TokenCipher('secret'));
        $cfg->setActiveEventUid('e1');
        $tenantSvc = new TenantService($pdo);
        $service = new TeamService($pdo, $cfg, $tenantSvc, 'sub1');
        $resultService = new ResultService($pdo);
        $controller = new TeamController($service, $cfg, $resultService);

        $callableResolver = new \Slim\CallableResolver();
        $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
        $handler = new \App\Application\Handlers\HttpErrorHandler(
            $callableResolver,
            $responseFactory
        );

        $request = $this->createRequest('POST', '/teams.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['A', 'B', 'C', 'D', 'E', 'F']));
        $request = $request->withBody($stream);

        try {
            $controller->post($request, new Response());
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            ob_start();
            $response = $handler($request, $e, false, false, false);
            ob_end_clean();
            $this->assertEquals(402, $response->getStatusCode());
            $this->assertStringContainsString('max-teams-exceeded', (string) $response->getBody());
        }
    }

    public function testPostRemovesDeletedTeamResults(): void {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE teams(uid TEXT PRIMARY KEY, event_uid TEXT, sort_order INTEGER, name TEXT)');
        $pdo->exec('CREATE TABLE results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
            'name TEXT NOT NULL,' .
            'catalog TEXT NOT NULL,' .
            'attempt INTEGER NOT NULL,' .
            'correct INTEGER NOT NULL,' .
            'total INTEGER NOT NULL,' .
            'time INTEGER NOT NULL,' .
            'points INTEGER NOT NULL,' .
            'max_points INTEGER NOT NULL,' .
            'event_uid TEXT' .
            ')');
        $pdo->exec('CREATE TABLE question_results(' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
            'name TEXT NOT NULL,' .
            'catalog TEXT NOT NULL,' .
            'question_id INTEGER NOT NULL,' .
            'attempt INTEGER NOT NULL,' .
            'correct INTEGER NOT NULL,' .
            'points INTEGER NOT NULL,' .
            'event_uid TEXT' .
            ')');
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','event-1','Event 1')");
        $cfg = new ConfigService($pdo, new TokenCipher('secret'));
        $cfg->setActiveEventUid('e1');
        $teamService = new TeamService($pdo, $cfg);
        $resultService = new ResultService($pdo);
        $controller = new TeamController($teamService, $cfg, $resultService);

        $teamService->saveAll(['Alpha', 'Bravo']);

        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,total,time,points,max_points,event_uid) "
            . "VALUES('Alpha','catalog-a',1,1,1,0,5,5,'e1')"
        );
        $pdo->exec(
            "INSERT INTO results(name,catalog,attempt,correct,total,time,points,max_points,event_uid) "
            . "VALUES('Bravo','catalog-b',1,1,1,0,4,4,'e1')"
        );
        $pdo->exec(
            "INSERT INTO question_results(name,catalog,question_id,attempt,correct,points,event_uid) "
            . "VALUES('Alpha','catalog-a',1,1,1,5,'e1')"
        );
        $pdo->exec(
            "INSERT INTO question_results(name,catalog,question_id,attempt,correct,points,event_uid) "
            . "VALUES('Bravo','catalog-b',1,1,1,4,'e1')"
        );

        $request = $this->createRequest('POST', '/teams.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['Alpha']));
        $request = $request->withBody($stream);

        $response = $controller->post($request, new Response());

        $this->assertSame(204, $response->getStatusCode());

        $remainingTeams = $pdo->query("SELECT name FROM results WHERE event_uid='e1' ORDER BY name")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['Alpha'], $remainingTeams);

        $remainingQuestionTeams = $pdo->query("SELECT name FROM question_results WHERE event_uid='e1' ORDER BY name")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['Alpha'], $remainingQuestionTeams);
    }
}
