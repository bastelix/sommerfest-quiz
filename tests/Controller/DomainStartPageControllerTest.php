<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\DomainStartPageService;
use App\Service\PageService;
use Tests\TestCase;

class DomainStartPageControllerTest extends TestCase
{
    public function testCanSaveNewMarketingPageAsStartPage(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id('domainstartpage');
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_COOKIE[session_name()] = session_id();

        $previousMainDomain = getenv('MAIN_DOMAIN');
        $previousEnvMainDomain = $_ENV['MAIN_DOMAIN'] ?? null;
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

        try {
            $pdo = $this->getDatabase();
            $pageService = new PageService($pdo);
            $pageService->create('fresh-marketing', 'Fresh Marketing', '<p>Landing</p>');

            $request = $this->createRequest('POST', '/admin/domain-start-pages', [
                'Content-Type' => 'application/json',
            ]);
            $payload = json_encode([
                'domain' => 'example.com',
                'start_page' => 'fresh-marketing',
                'email' => '',
            ], JSON_THROW_ON_ERROR);
            $request->getBody()->write($payload);
            $request->getBody()->rewind();

            $app = $this->getAppInstance();
            $response = $app->handle($request);

            $this->assertSame(200, $response->getStatusCode());

            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('ok', $data['status'] ?? null);
            $this->assertSame('fresh-marketing', $data['config']['start_page'] ?? null);
            $this->assertArrayHasKey('has_smtp_pass', $data['config']);
            $this->assertFalse($data['config']['has_smtp_pass']);
            $this->assertArrayHasKey('fresh-marketing', $data['options'] ?? []);

            $service = new DomainStartPageService($pdo);
            $config = $service->getDomainConfig('example.com');
            $this->assertNotNull($config);
            $this->assertSame('fresh-marketing', $config['start_page']);
            $this->assertNull($config['smtp_host']);
            $this->assertNull($config['smtp_user']);
            $this->assertNull($config['smtp_dsn']);
            $this->assertNull($config['smtp_encryption']);
            $this->assertNull($config['smtp_pass']);
            $this->assertFalse($config['has_smtp_pass']);

            $settingsValue = $pdo->query("SELECT value FROM settings WHERE key = 'home_page'")?->fetchColumn();
            $this->assertSame('fresh-marketing', $settingsValue);
        } finally {
            if ($previousMainDomain === false) {
                putenv('MAIN_DOMAIN');
            } else {
                putenv('MAIN_DOMAIN=' . $previousMainDomain);
            }
            if ($previousEnvMainDomain === null) {
                unset($_ENV['MAIN_DOMAIN']);
            } else {
                $_ENV['MAIN_DOMAIN'] = $previousEnvMainDomain;
            }
            session_destroy();
        }
    }
}
