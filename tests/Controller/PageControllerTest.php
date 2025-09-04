<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class PageControllerTest extends TestCase
{
    public function testEditAndUpdate(): void
    {
        $pdo = $this->getDatabase();
        $pdo->exec("INSERT INTO pages(slug, content) VALUES('landing','<p>old</p>')");

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $response = $app->handle($this->createRequest('GET', '/admin/pages/landing'));
        $this->assertEquals(200, $response->getStatusCode());

        $req = $this->createRequest('POST', '/admin/pages/landing', [
            'HTTP_X_CSRF_TOKEN' => 'token',
        ]);
        $req = $req->withParsedBody(['content' => '<p>new</p>']);
        $res = $app->handle($req);
        $this->assertEquals(204, $res->getStatusCode());
        $content = $pdo->query("SELECT content FROM pages WHERE slug='landing'")->fetchColumn();
        $this->assertSame('<p>new</p>', $content);

        session_destroy();
    }

    public function testInvalidSlug(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $res = $app->handle($this->createRequest('GET', '/admin/pages/unknown'));
        $this->assertEquals(404, $res->getStatusCode());
        session_destroy();
    }
}
