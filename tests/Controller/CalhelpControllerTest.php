<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class CalhelpControllerTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        $pdo = $this->getDatabase();
        try {
            $pdo->exec("INSERT INTO pages(slug,title,content) VALUES('calhelp','calHelp','<p>calHelp</p>')");
        } catch (\PDOException $e) {
            // Ignore duplicates when running multiple tests with shared databases.
        }
    }

    public function testCalhelpPage(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calhelp');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('calHelp – Marketingseite', $body);
        $this->assertStringContainsString('data-marketing-chat-open', $body);
        $this->assertStringNotContainsString("csrfToken: ''", $body);
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testCalhelpPageTenant(): void {
        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calhelp');
        $request = $request->withUri($request->getUri()->withHost('tenant.main.test'));
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    public function testCalhelpPageContentSynchronizesFromFile(): void {
        $pdo = $this->getDatabase();
        $pdo->exec("UPDATE pages SET content = '<p>outdated</p>' WHERE slug = 'calhelp'");

        $old = getenv('MAIN_DOMAIN');
        putenv('MAIN_DOMAIN=main.test');

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/calhelp');
        $request = $request->withUri($request->getUri()->withHost('main.test'));
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $stmt = $pdo->prepare('SELECT content FROM pages WHERE slug = ?');
        $stmt->execute(['calhelp']);
        $storedContent = (string) $stmt->fetchColumn();

        $filePath = dirname(__DIR__, 2) . '/content/marketing/calhelp.html';
        $this->assertFileExists($filePath);
        $fileContent = file_get_contents($filePath);
        $this->assertNotFalse($fileContent);

        $this->assertSame(
            $this->normalizeHtml($fileContent),
            $this->normalizeHtml($storedContent)
        );

        $body = (string) $response->getBody();
        $this->assertStringContainsString('Erkennen Sie sich wieder?', $body);

        if ($old === false) {
            putenv('MAIN_DOMAIN');
        } else {
            putenv('MAIN_DOMAIN=' . $old);
        }
    }

    private function normalizeHtml(string $html): string {
        return trim(str_replace(["\r\n", "\r"], "\n", $html));
    }
}
