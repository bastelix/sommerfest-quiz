<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    public function testAdminPage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/admin');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
