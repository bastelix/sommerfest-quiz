<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class DatenschutzControllerTest extends TestCase
{
    public function testDatenschutzPage(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/datenschutz');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
