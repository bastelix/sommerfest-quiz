<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Admin\UsernameBlocklistController;
use App\Service\ConfigService;
use App\Service\TranslationService;
use App\Service\UsernameBlocklistService;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;

final class UsernameBlocklistControllerTest extends TestCase
{
    public function testIndexRendersWithTenantConfig(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE events (uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE config (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_uid TEXT,
            backgroundColor TEXT,
            buttonColor TEXT,
            colors TEXT
        )');
        $pdo->exec('CREATE TABLE username_blocklist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            term TEXT NOT NULL,
            category TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $eventUid = '1234567890abcdef1234567890abcdef';
        $pdo->exec("INSERT INTO events (uid) VALUES ('$eventUid')");
        $pdo->exec("INSERT INTO config (event_uid, backgroundColor, buttonColor, colors) VALUES ('$eventUid', '#112233', '#445566', NULL)");

        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';

        $configService = new ConfigService($pdo);
        $configService->setActiveEventUid($eventUid);

        $blocklistService = new UsernameBlocklistService($pdo);
        $translationService = new TranslationService();

        $twig = Twig::create(__DIR__ . '/../../templates', ['cache' => false]);
        $twig->addExtension(new \App\Twig\UikitExtension());
        $twig->addExtension(new \App\Twig\DateTimeFormatExtension());
        $twig->addExtension(new \App\Twig\TranslationExtension($translationService));
        $twig->getEnvironment()->addGlobal('basePath', '');
        $twig->getEnvironment()->addGlobal('baseUrl', 'https://example.com');

        $_SESSION = ['user' => ['role' => 'admin']];

        $controller = new UsernameBlocklistController(
            $blocklistService,
            $configService,
            $translationService
        );

        $requestFactory = new ServerRequestFactory();
        $request = $requestFactory->createServerRequest('GET', '/admin/username-blocklist')
            ->withAttribute('view', $twig)
            ->withAttribute('translator', $translationService)
            ->withAttribute('domainType', 'main');

        $response = new Response();
        $response = $controller->index($request, $response);

        $this->assertSame(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $this->assertStringContainsString('data-username-blocklist', $body);
        $this->assertStringContainsString('--color-bg: #112233;', $body);

        putenv('DASHBOARD_TOKEN_SECRET');
        unset($_ENV['DASHBOARD_TOKEN_SECRET']);
    }
}
