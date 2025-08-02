<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class PageControllerTest extends TestCase
{
    public function testEditAndUpdate(): void
    {
        $dir = dirname(__DIR__, 2) . '/content';
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $file = $dir . '/landing.html';
        $original = file_exists($file) ? file_get_contents($file) : null;
        file_put_contents($file, '<p>old</p>');
        $this->assertFileExists($file);

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

        $response = $app->handle($this->createRequest('GET', '/admin/pages/landing'));
        $this->assertEquals(200, $response->getStatusCode());

        $req = $this->createRequest('POST', '/admin/pages/landing');
        $req = $req->withParsedBody(['content' => '<p>new</p>']);
        $res = $app->handle($req);
        $this->assertEquals(204, $res->getStatusCode());
        $this->assertStringEqualsFile($file, '<p>new</p>');

        $this->destroySession();
        if ($original === null) {
            unlink($file);
            $this->assertFileDoesNotExist($file);
        } else {
            file_put_contents($file, $original);
            $this->assertFileExists($file);
        }
    }

    public function testInvalidSlug(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $res = $app->handle($this->createRequest('GET', '/admin/pages/unknown'));
        $this->assertEquals(404, $res->getStatusCode());
        $this->destroySession();
    }
}
