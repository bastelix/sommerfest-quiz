<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class LandingControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $pdo = $this->getDatabase();
        try {
            $stmt = $pdo->prepare('INSERT INTO pages(slug,title,content) VALUES(?,?,?)');
            $content = <<<'HTML'
<p>Landing <a href="/faq">FAQ</a></p>
<form id="contact-form" data-contact-endpoint="/landing/contact"><input type="text" name="name"></form>
HTML;
            $stmt->execute(['landing', 'Landing', $content]);
        } catch (\PDOException $e) {
            // Ignore duplicates when running multiple tests with shared databases.
        }
    }

    public function testLandingPage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLandingPageTenant(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $request = $request->withUri($request->getUri()->withHost('tenant.main.test'));
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testContactFormHiddenWhenMailConfigMissing(): void
    {
        $host = getenv('SMTP_HOST');
        $user = getenv('SMTP_USER');
        $pass = getenv('SMTP_PASS');
        putenv('SMTP_HOST');
        putenv('SMTP_USER');
        putenv('SMTP_PASS');
        unset($_ENV['SMTP_HOST'], $_ENV['SMTP_USER'], $_ENV['SMTP_PASS']);

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $response = $app->handle($request);
        $body = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('id="contact-form"', $body);
        $this->assertStringContainsString('Kontaktformular derzeit nicht verfügbar', $body);

        if ($host !== false) {
            putenv('SMTP_HOST=' . $host);
            $_ENV['SMTP_HOST'] = $host;
        }
        if ($user !== false) {
            putenv('SMTP_USER=' . $user);
            $_ENV['SMTP_USER'] = $user;
        }
        if ($pass !== false) {
            putenv('SMTP_PASS=' . $pass);
            $_ENV['SMTP_PASS'] = $pass;
        }
    }

    public function testContactFormVisibleWhenMailConfigured(): void
    {
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_USER=user@example.org');
        putenv('SMTP_PASS=secret');
        $_ENV['SMTP_HOST'] = 'localhost';
        $_ENV['SMTP_USER'] = 'user@example.org';
        $_ENV['SMTP_PASS'] = 'secret';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $response = $app->handle($request);
        $body = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('id="contact-form"', $body);
        $this->assertStringContainsString('data-contact-endpoint="/landing/contact"', $body);

        putenv('SMTP_HOST');
        putenv('SMTP_USER');
        putenv('SMTP_PASS');
        unset($_ENV['SMTP_HOST'], $_ENV['SMTP_USER'], $_ENV['SMTP_PASS']);
    }

    public function testLandingPageContainsFaqLink(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/landing');
        $response = $app->handle($request);
        $body = (string) $response->getBody();
        $this->assertStringContainsString('href="/faq"', $body);
    }

    public function testMarketingRouteAliasReturnsLandingPage(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/m/landing');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('href="/faq"', $body);
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testUnknownMarketingSlugReturns404(): void
    {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/m/unknown');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }
}
