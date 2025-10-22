<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class GlobalMediaControllerTest extends TestCase
{
    public function testServesGlobalUpload(): void {
        $dir = __DIR__ . '/../../data/uploads';
        $createdDir = false;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            $createdDir = true;
        }
        $file = $dir . '/sample.txt';
        file_put_contents($file, 'demo');

        putenv('MAIN_DOMAIN=localhost');
        $_ENV['MAIN_DOMAIN'] = 'localhost';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/uploads/sample.txt');
        $request = $request->withUri(new \Slim\Psr7\Uri('http', 'localhost', 80, '/uploads/sample.txt'));
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('demo', (string) $response->getBody());
        $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
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
        if ($createdDir) {
            rmdir($dir);
        }

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

    public function testConditionalRequestReturnsNotModified(): void {
        $dir = __DIR__ . '/../../data/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/sample.txt';
        file_put_contents($file, 'demo');

        putenv('MAIN_DOMAIN=localhost');
        $_ENV['MAIN_DOMAIN'] = 'localhost';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/uploads/sample.txt');
        $request = $request->withUri(new \Slim\Psr7\Uri('http', 'localhost', 80, '/uploads/sample.txt'));
        $initial = $app->handle($request);

        $etag = $initial->getHeaderLine('ETag');
        $this->assertNotSame('', $etag);

        $conditional = $this->createRequest('GET', '/uploads/sample.txt')
            ->withUri(new \Slim\Psr7\Uri('http', 'localhost', 80, '/uploads/sample.txt'))
            ->withHeader('If-None-Match', $etag);
        $response = $app->handle($conditional);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertSame($etag, $response->getHeaderLine('ETag'));

        unlink($file);
        if (count(scandir($dir)) === 2) {
            rmdir($dir);
        }

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }
}
