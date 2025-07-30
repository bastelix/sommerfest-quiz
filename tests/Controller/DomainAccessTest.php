<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class DomainAccessTest extends TestCase
{
    public function testMarketingRoutesOnMainDomain(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testTenantApiRejectedOnSubdomain(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('POST', '/tenants');
        $request = $request->withUri($request->getUri()->withHost('tenant.test'));
        $response = $app->handle($request);
        $this->assertEquals(403, $response->getStatusCode());
        session_destroy();
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testTenantCheckRejectedOnSubdomain(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $req = $this->createRequest('GET', '/tenants/foo');
        $req = $req->withUri($req->getUri()->withHost('tenant.test'));
        $res = $app->handle($req);
        $this->assertEquals(403, $res->getStatusCode());
        session_destroy();
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
