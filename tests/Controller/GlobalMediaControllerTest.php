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

        unlink($file);
        if ($createdDir) {
            rmdir($dir);
        }

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }
}
