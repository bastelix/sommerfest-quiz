<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class CalserverControllerTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        $pdo = $this->getDatabase();
        try {
            $pdo->exec("INSERT INTO pages(slug,title,content) VALUES('calserver','calServer','<p>calServer</p>')");
        } catch (\PDOException $e) {
            // Ignore duplicates when running multiple tests with shared databases.
        }
    }

    public function testCalserverPage(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calserver');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('calServer – Marketingseite', $body);
        $this->assertStringContainsString('data-calserver-cookie-banner', $body);
        $this->assertStringNotContainsString("csrfToken: ''", $body);
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testCalserverPageTenant(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calserver');
        $request = $request->withUri($request->getUri()->withHost('tenant.main.test'));
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
