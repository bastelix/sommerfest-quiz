<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class QrControllerTest extends TestCase
{
    public function testQrImageIsGenerated(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.png?t=Test');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }
}
