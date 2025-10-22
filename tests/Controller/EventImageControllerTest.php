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
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));

        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $response->getHeaderLine('ETag'));
        $this->assertSame(
            '"' . hash_file('sha256', $file) . '"',
            $response->getHeaderLine('ETag')
        );

        $mtime = filemtime($file) ?: 0;
        $this->assertSame(
            gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            $response->getHeaderLine('Last-Modified')
        );

        unlink($file);
        rmdir($dir);
        rmdir(dirname($dir));

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

    public function testConditionalEventImageRequest(): void {
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
        $initial = $app->handle($request);

        $etag = $initial->getHeaderLine('ETag');
        $this->assertNotSame('', $etag);

        $conditional = $this->createRequest('GET', '/events/evimg/images/sticker-bg.png')
            ->withUri(new \Slim\Psr7\Uri('http', 'localhost', 80, '/events/evimg/images/sticker-bg.png'))
            ->withHeader('If-None-Match', $etag);
        $response = $app->handle($conditional);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertSame($etag, $response->getHeaderLine('ETag'));

        unlink($file);
        rmdir($dir);
        rmdir(dirname($dir));

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }
}
