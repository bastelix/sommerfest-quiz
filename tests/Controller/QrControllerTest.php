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

    public function testQrPdfIsGenerated(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.pdf?t=Test');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testQrPdfWithWebpLogo(): void
    {
        $configPath = __DIR__ . '/../../data/config.json';
        $backup = file_get_contents($configPath);
        $cfg = json_decode((string) $backup, true);
        $cfg['logoPath'] = '/logo.webp';
        file_put_contents($configPath, json_encode($cfg, JSON_PRETTY_PRINT));

        $logoPath = __DIR__ . '/../../data/logo.webp';
        $img = imagecreatetruecolor(1, 1);
        ob_start();
        imagewebp($img);
        $data = ob_get_clean();
        file_put_contents($logoPath, (string) $data);
        imagedestroy($img);

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/qr.pdf?t=Test');
        $response = $app->handle($request);

        unlink($logoPath);
        file_put_contents($configPath, (string) $backup);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }
}
