<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Domain\Roles;
use Tests\TestCase;

class EventsRouteTest extends TestCase
{
    public function testEventsListAccessibleForCatalogEditor(): void
    {
        $app = $this->getAppInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = ['role' => Roles::CATALOG_EDITOR];

        $request = $this->createRequest('GET', '/events.json', ['HTTP_ACCEPT' => 'application/json']);
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        @session_destroy();
    }
}
