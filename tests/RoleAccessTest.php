<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    private function setupDb(): string
    {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $db;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';
        return $db;
    }

    public function testCatalogEditorCanEditCatalog(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $req = $this->createRequest('POST', '/kataloge/test.json');
        $req = $req->withParsedBody([]);
        $res = $app->handle($req);
        $this->assertEquals(204, $res->getStatusCode());
        session_destroy();
        unlink($db);
    }

    public function testAnalystCannotEditCatalog(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'analyst'];
        $req = $this->createRequest('POST', '/kataloge/test.json');
        $req = $req->withParsedBody([]);
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        session_destroy();
        unlink($db);
    }

    public function testEventManagerCanUpdateConfig(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];
        $req = $this->createRequest('POST', '/config.json');
        $req = $req->withParsedBody([]);
        $res = $app->handle($req);
        $this->assertEquals(204, $res->getStatusCode());
        session_destroy();
        unlink($db);
    }

    public function testTeamManagerCanPostTeams(): void
    {
        $db = $this->setupDb();
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
        unlink($db);
    }

    public function testAnalystCanAccessResults(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'analyst'];
        $req = $this->createRequest('GET', '/results.json');
        $res = $app->handle($req);
        $this->assertEquals(200, $res->getStatusCode());
        session_destroy();
        unlink($db);
    }

    public function testAdminCanAccessSeoForm(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('POST', '/admin/landingpage/seo');
        $req = $req->withParsedBody(['pageId' => 1, 'slug' => 'test']);
        $res = $app->handle($req);
        $this->assertEquals(204, $res->getStatusCode());
        session_destroy();
        unlink($db);
    }

    public function testCatalogEditorCannotAccessSeoForm(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $req = $this->createRequest('POST', '/admin/landingpage/seo');
        $req = $req->withParsedBody(['pageId' => 1, 'slug' => 'test']);
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        session_destroy();
        unlink($db);
    }

    public function testAdminCanViewSeoForm(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('GET', '/admin/landingpage/seo');
        $res = $app->handle($req);
        $this->assertEquals(200, $res->getStatusCode());
        session_destroy();
        unlink($db);
    }

    public function testCatalogEditorCannotViewSeoForm(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $req = $this->createRequest('GET', '/admin/landingpage/seo');
        $res = $app->handle($req);
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/login', $res->getHeaderLine('Location'));
        session_destroy();
        unlink($db);
    }
}
