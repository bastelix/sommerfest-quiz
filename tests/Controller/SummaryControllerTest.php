<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class SummaryControllerTest extends TestCase
{
    public function testSummaryPage(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/summary');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
