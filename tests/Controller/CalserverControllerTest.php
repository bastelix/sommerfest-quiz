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
        try {
            $pdo->exec("INSERT INTO pages(slug,title,content) VALUES('calserver-accessibility','calServer Barrierefreiheit','<h1>Barrierefreiheit</h1>')");
        } catch (\PDOException $e) {
            // Ignore duplicates when running multiple tests with shared databases.
        }
        $pdo->exec("UPDATE pages SET title='calServer Barrierefreiheit', content='<h1>Barrierefreiheit</h1>' WHERE slug='calserver-accessibility'");
        try {
            $pdo->exec("INSERT INTO pages(slug,title,content) VALUES('calserver-accessibility-en','calServer Accessibility','<h1>Accessibility</h1>')");
        } catch (\PDOException $e) {
            // Ignore duplicates when running multiple tests with shared databases.
        }
        $pdo->exec("UPDATE pages SET title='calServer Accessibility', content='<h1>Accessibility</h1>' WHERE slug='calserver-accessibility-en'");
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

    public function testCalserverAccessibilityPage(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calserver/barrierefreiheit?lang=de');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Barrierefreiheit', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*lang="de"[^>]*aria-current="page"/m', $body);
        $this->assertStringContainsString('/calserver/accessibility?lang=en', $body);
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testCalserverAccessibilityPageEnglish(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calserver/accessibility?lang=en');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Accessibility', $body);
        $this->assertMatchesRegularExpression('/<a[^>]*lang="en"[^>]*aria-current="page"/m', $body);
        $this->assertStringContainsString('/calserver/barrierefreiheit?lang=de', $body);
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
