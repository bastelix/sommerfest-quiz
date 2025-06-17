<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class HelpControllerTest extends TestCase
{
    public function testHelpPage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/help');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
