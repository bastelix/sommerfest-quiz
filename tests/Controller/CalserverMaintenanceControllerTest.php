<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class CalserverMaintenanceControllerTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        $pdo = $this->getDatabase();
        foreach (
            [
            ['calserver-maintenance', 'calHelp Wartung'],
            ['calserver-maintenance-en', 'calHelp Maintenance'],
            ] as [$slug, $title]
        ) {
            try {
                $pdo->exec(
                    sprintf(
                        "INSERT INTO pages(slug,title,content) VALUES('%s','%s','<p>%s</p>')",
                        $slug,
                        $title,
                        $title
                    )
                );
            } catch (\PDOException $e) {
                // Ignore duplicates for shared databases across tests.
            }
        }
    }

    public function testMaintenancePageAccessible(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calserver-maintenance');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Wartungshinweis', $body);
        $this->assertStringContainsString('Status-Updates', $body);
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testMaintenancePageTenantDenied(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calserver-maintenance');
        $request = $request->withUri($request->getUri()->withHost('tenant.main.test'));
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testMaintenancePageEnglishFallback(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calserver-maintenance?lang=en');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('calHelp Maintenance', $body);
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
