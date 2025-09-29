<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class LizenzControllerTest extends TestCase
{
    public function testLizenzPage(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/lizenz');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
