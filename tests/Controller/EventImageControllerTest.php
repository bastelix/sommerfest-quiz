<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class EventImageControllerTest extends TestCase
{
    public function testServesEventImage(): void {
        $dir = __DIR__ . '/../../data/events/evimg/images';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/sticker-bg.png';
        imagepng(imagecreatetruecolor(5, 5), $file);

        putenv('MAIN_DOMAIN=localhost');
        $_ENV['MAIN_DOMAIN'] = 'localhost';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/events/evimg/images/sticker-bg.png');
        $request = $request->withUri(
            new \Slim\Psr7\Uri('http', 'localhost', 80, '/events/evimg/images/sticker-bg.png')
        );
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());

        unlink($file);
        rmdir($dir);
        rmdir(dirname($dir));

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }
}
