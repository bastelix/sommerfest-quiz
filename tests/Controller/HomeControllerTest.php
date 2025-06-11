<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    public function testHomePage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
