<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\EventConfigController;
use App\Service\ConfigService;
use App\Service\EventService;
use Slim\Psr7\Response;
use Tests\TestCase;

class EventConfigControllerTest extends TestCase
{
    public function testUpdateInvalidData(): void
    {
        $pdo = $this->createDatabase();
        $eventService = new EventService($pdo);
        $eventService->saveAll([['uid' => 'ev1', 'name' => 'Test']]);
        $controller = new EventConfigController($eventService, new ConfigService($pdo));

        $request = $this->createRequest('PUT', '/events/ev1/config.json');
        $request = $request->withParsedBody(['backgroundColor' => 'blue']);

        $response = $controller->update($request, new Response(), ['id' => 'ev1']);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('errors', (string) $response->getBody());
    }

    public function testUpdateValidData(): void
    {
        $pdo = $this->createDatabase();
        $eventService = new EventService($pdo);
        $eventService->saveAll([['uid' => 'ev1', 'name' => 'Test']]);
        $configService = new ConfigService($pdo);
        $controller = new EventConfigController($eventService, $configService);

        $request = $this->createRequest('PUT', '/events/ev1/config.json');
        $request = $request->withParsedBody(['pageTitle' => 'Demo']);

        $response = $controller->update($request, new Response(), ['id' => 'ev1']);

        $this->assertEquals(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('ev1', $payload['event']['uid'] ?? null);
    }
}
