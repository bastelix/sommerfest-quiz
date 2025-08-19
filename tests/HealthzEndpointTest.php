<?php

declare(strict_types=1);

namespace Tests;

use Tests\TestCase;

class HealthzEndpointTest extends TestCase
{
    public function testHealthzEndpointReturnsOkJson(): void
    {
        $app = $this->getAppInstance();
        $res = $app->handle($this->createRequest('GET', '/healthz'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/json', $res->getHeaderLine('Content-Type'));
        $data = json_decode((string) $res->getBody(), true);
        $this->assertSame('ok', $data['status'] ?? null);
        $this->assertSame('quizrace', $data['app'] ?? null);
    }
}
