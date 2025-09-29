<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    public function testCatalogEditorCanEditCatalog(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $req = $this->createRequest('POST', '/kataloge/test.json');
        $req = $req->withParsedBody([]);
        $res = $app->handle($req);
        $this->assertEquals(204, $res->getStatusCode());
        session_destroy();
    }

    public function testAnalystCannotEditCatalog(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'analyst'];
        $req = $this->createRequest('POST', '/kataloge/test.json');
        $req = $req->withParsedBody([]);
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        session_destroy();
    }

    public function testEventManagerCanUpdateConfig(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];
        $req = $this->createRequest('POST', '/config.json');
        $req = $req->withParsedBody([]);
        $res = $app->handle($req);
        $this->assertEquals(204, $res->getStatusCode());
        session_destroy();
    }

    public function testTeamManagerCanPostTeams(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'team-manager'];
        $req = $this->createRequest('POST', '/teams.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '[]');
        rewind($stream);
        $req = $req->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream));
        $res = $app->handle($req);
        $this->assertEquals(204, $res->getStatusCode());
        session_destroy();
    }

    public function testAnalystCanAccessResults(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'analyst'];
        $req = $this->createRequest('GET', '/results.json');
        $res = $app->handle($req);
        $this->assertEquals(200, $res->getStatusCode());
        session_destroy();
    }

    public function testAdminCanAccessSeoForm(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';
        $pdo = $this->getDatabase();
        $pdo->exec("INSERT OR IGNORE INTO pages (id, slug, title, content) VALUES (1, 'landing', 'Landing', '')");
        $req = $this->createRequest('POST', '/admin/landingpage/seo', [
            'HTTP_X_CSRF_TOKEN' => 'token',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $req = $req->withParsedBody([
            'pageId' => 1,
            'slug' => 'test',
            'meta_title' => 'Title',
            'meta_description' => 'Description',
        ]);
        $res = $app->handle($req);
        $this->assertEquals(200, $res->getStatusCode());
        $payload = json_decode((string) $res->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertSame('ok', $payload['status'] ?? null);
        session_destroy();
    }

    public function testCatalogEditorCannotAccessSeoForm(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $_SESSION['csrf_token'] = 'token';
        $req = $this->createRequest('POST', '/admin/landingpage/seo', [
            'HTTP_X_CSRF_TOKEN' => 'token',
        ]);
        $req = $req->withParsedBody(['pageId' => 1, 'slug' => 'test']);
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        session_destroy();
    }

    public function testAdminCanViewSeoForm(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('GET', '/admin/landingpage/seo');
        $res = $app->handle($req);
        $this->assertEquals(200, $res->getStatusCode());
        session_destroy();
    }

    public function testCatalogEditorCannotViewSeoForm(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $req = $this->createRequest('GET', '/admin/landingpage/seo');
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        session_destroy();
    }
}
