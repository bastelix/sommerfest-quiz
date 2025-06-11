<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class ImpressumControllerTest extends TestCase
{
    public function testImpressumPage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/impressum');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
