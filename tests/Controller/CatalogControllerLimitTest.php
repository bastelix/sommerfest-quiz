<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\CatalogController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\TenantService;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;
use Tests\TestCase;

class CatalogControllerLimitTest extends TestCase
{
    public function testPostCatalogsLimitExceededReturns402(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('e1')");
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','starter')");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $tenantSvc = new TenantService($pdo);
        $service = new CatalogService($pdo, $cfg, $tenantSvc, 'sub1');
        $controller = new CatalogController($service);
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];

        $callableResolver = new \Slim\CallableResolver();
        $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
        $handler = new \App\Application\Handlers\HttpErrorHandler(
            $callableResolver,
            $responseFactory
        );

        $request = $this->createRequest('POST', '/catalogs.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode([
            ['slug' => 'c1', 'file' => 'c1.json'],
            ['slug' => 'c2', 'file' => 'c2.json'],
            ['slug' => 'c3', 'file' => 'c3.json'],
            ['slug' => 'c4', 'file' => 'c4.json'],
            ['slug' => 'c5', 'file' => 'c5.json'],
            ['slug' => 'c6', 'file' => 'c6.json'],
        ]));
        $request = $request->withBody($stream);

        try {
            $controller->post($request, new Response(), ['file' => 'catalogs.json']);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            ob_start();
            $response = $handler($request, $e, false, false, false);
            ob_end_clean();
            $this->assertEquals(402, $response->getStatusCode());
            $this->assertStringContainsString('max-catalogs-exceeded', (string) $response->getBody());
        }

        session_destroy();
    }

    public function testPostQuestionsLimitExceededReturns402(): void {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event1')");
        $pdo->exec("INSERT INTO config(event_uid) VALUES('e1')");
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','starter')");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $tenantSvc = new TenantService($pdo);
        $service = new CatalogService($pdo, $cfg, $tenantSvc, 'sub1');
        $service->createCatalog('test.json');
        $controller = new CatalogController($service);
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];

        $callableResolver = new \Slim\CallableResolver();
        $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
        $handler = new \App\Application\Handlers\HttpErrorHandler(
            $callableResolver,
            $responseFactory
        );

        $request = $this->createRequest('POST', '/test.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $questions = [];
        for ($i = 1; $i <= 6; $i++) {
            $questions[] = ['type' => 'text', 'prompt' => 'Q' . $i];
        }
        $stream = (new StreamFactory())->createStream(json_encode($questions));
        $request = $request->withBody($stream);

        try {
            $controller->post($request, new Response(), ['file' => 'test.json']);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            ob_start();
            $response = $handler($request, $e, false, false, false);
            ob_end_clean();
            $this->assertEquals(402, $response->getStatusCode());
            $this->assertStringContainsString('max-questions-exceeded', (string) $response->getBody());
        }

        session_destroy();
    }
}
