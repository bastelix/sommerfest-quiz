<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class LandingControllerTest extends TestCase
{
    public function testLandingPage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testLandingPageTenant(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $request = $request->withUri($request->getUri()->withHost('tenant.test'));
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
