<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\TeamController;
use App\Service\ConfigService;
use App\Service\TeamService;
use App\Service\TenantService;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    public function testPostExceedingTeamLimitReturns402(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('e1')");
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','starter')");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $tenantSvc = new TenantService($pdo);
        $service = new TeamService($pdo, $cfg, $tenantSvc, 'sub1');
        $controller = new TeamController($service);

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
}
