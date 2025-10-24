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

    public function testSummaryPageForceResultsParameter(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/summary?results=1');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('window.forceResults = true;', $body);
    }
}
