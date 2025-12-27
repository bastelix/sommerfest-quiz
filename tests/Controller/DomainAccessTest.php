<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\DomainService;
use Tests\TestCase;

class DomainAccessTest extends TestCase
{
    public function testMarketingRoutesOnMainDomain(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $pdo = $this->getDatabase();
        try {
            $pdo->exec("INSERT INTO pages(slug,title,content) VALUES('landing','Landing','<p>Landing</p>')");
        } catch (\PDOException $e) {
            // Ignore duplicate inserts when the page already exists.
        }
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testCalserverRouteOnMainDomain(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calserver');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testTenantApiRejectedOnSubdomain(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('POST', '/tenants');
        $request = $request->withUri($request->getUri()->withHost('tenant.main.test'));
        $response = $app->handle($request);
        $this->assertEquals(403, $response->getStatusCode());
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testTenantCheckRejectedOnSubdomain(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('GET', '/tenants/foo');
        $req = $req->withUri($req->getUri()->withHost('tenant.main.test'));
        $res = $app->handle($req);
        $this->assertEquals(403, $res->getStatusCode());
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testMarketingSlugOnMarketingDomain(): void {
        $oldMain = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $pdo = $this->getDatabase();
        $domainService = new DomainService($pdo);
        $domainService->createDomain('marketing.test');
        try {
            $pdo->exec("INSERT INTO pages(slug,title,content) VALUES('landing','Landing','<p>Landing</p>')");
        } catch (\PDOException $e) {
            // Ignore duplicates when running tests multiple times.
        }
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $request = $request->withUri($request->getUri()->withHost('marketing.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<p>Landing', $body);
        if ($oldMain === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $oldMain);
        }
    }

    public function testUnknownSlugOnMarketingDomainReturns404(): void {
        $oldMain = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $pdo = $this->getDatabase();
        $domainService = new DomainService($pdo);
        $domainService->createDomain('marketing.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/does-not-exist');
        $request = $request->withUri($request->getUri()->withHost('marketing.test'));
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        if ($oldMain === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $oldMain);
        }
    }
}
