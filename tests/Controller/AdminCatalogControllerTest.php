<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\AdminCatalogController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Infrastructure\Migrations\Migrator;
use PDO;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

class AdminCatalogControllerTest extends TestCase
{
    public function testCatalogPageRenders(): void
    {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $pdo = new PDO('sqlite:' . $db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $pdo->exec("INSERT INTO events(uid,name) VALUES('e1','Event')");

        $cfgSvc = new ConfigService($pdo);
        $service = $this->createMock(CatalogService::class);
        $service->method('read')->willReturn(json_encode([
            ['uid' => 'c1', 'sort_order' => 1, 'slug' => 'slug', 'file' => 'file', 'name' => 'Cat', 'beschreibung' => '']
        ]));
        $controller = new AdminCatalogController($service);
        $twig = Twig::create(dirname(__DIR__, 2) . '/templates', ['cache' => false]);

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $request = $this->createRequest('GET', '/admin/kataloge')
            ->withAttribute('view', $twig)
            ->withAttribute('lang', 'de');
        $response = $controller($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        session_destroy();
        unlink($db);
    }

    public function testQrOptionsForwarded(): void
    {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $pdo = new PDO('sqlite:' . $db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $pdo->exec("INSERT INTO events(uid,name) VALUES('e1','Event')");

        $cfgSvc = new ConfigService($pdo);
        $service = $this->createMock(CatalogService::class);
        $service->method('read')->willReturn(json_encode([]));
        $controller = new AdminCatalogController($service);

        $captured = [];
        $twig = $this->createMock(Twig::class);
        $twig->method('render')->willReturnCallback(
            function (Response $res, string $tpl, array $data) use (&$captured) {
                $captured = $data;
                return $res;
            }
        );

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'catalog-editor'];
        $request = $this->createRequest('GET', '/admin/kataloge?event=e1&size=123&round_mode=margin')
            ->withAttribute('view', $twig)
            ->withAttribute('lang', 'de');
        $response = $controller($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('123', $captured['qrOptions']['size']);
        $this->assertSame('margin', $captured['qrOptions']['round_mode']);
        session_destroy();
        unlink($db);
    }
}
