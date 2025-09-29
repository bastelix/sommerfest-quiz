<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\AdminCatalogController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Infrastructure\Migrations\Migrator;
use PDO;
use Slim\Psr7\Response;
use Tests\TestCase;

class AdminCatalogControllerTest extends TestCase
{
    public function testCatalogsEndpointReturnsPagedJson(): void {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $pdo = new PDO('sqlite:' . $db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event')");
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('c1',1,'s1','s1.json','Cat1','e1')");
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('c2',2,'s2','s2.json','Cat2','e1')");
        $pdo->exec("INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) VALUES('c3',3,'s3','s3.json','Cat3','e1')");

        $cfgSvc = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfgSvc);
        $controller = new AdminCatalogController($service);

        $request = $this->createRequest('GET', '/admin/catalogs/data?page=1&perPage=2&order=asc');
        $response = $controller->catalogs($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertCount(2, $data['items']);
        $this->assertEquals(3, $data['total']);
        unlink($db);
    }
}
