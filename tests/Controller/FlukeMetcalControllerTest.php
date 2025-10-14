<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class FlukeMetcalControllerTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        $pdo = $this->getDatabase();
        try {
            $pdo->exec("INSERT INTO pages(slug, title, content) VALUES ('fluke-metcal', 'FLUKE MET/CAL', '<p>Hybridbetrieb</p>')");
        } catch (\PDOException $exception) {
            // Ignore duplicates when tests share a database state.
        }
    }

    public function testMetcalPageAccessible(): void {
        $previous = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/fluke-metcal');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Hybridbetrieb', $body);
        if ($previous === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $previous);
        }
    }

    public function testMetcalPageTenantDenied(): void {
        $previous = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/fluke-metcal');
        $request = $request->withUri($request->getUri()->withHost('tenant.main.test'));
        $response = $app->handle($request);
        $this->assertSame(404, $response->getStatusCode());
        if ($previous === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $previous);
        }
    }

    public function testMetcalPageEnglishFallback(): void {
        $previous = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/fluke-metcal?lang=en');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('FLUKE MET/CAL', $body);
        if ($previous === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $previous);
        }
    }
}
