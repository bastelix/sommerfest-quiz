<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\AdminCatalogController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use PDO;
use PDOException;
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
        $pdo->exec(
            'CREATE TABLE events('
            . 'uid TEXT PRIMARY KEY,'
            . 'slug TEXT UNIQUE NOT NULL,'
            . 'name TEXT NOT NULL,'
            . 'sort_order INTEGER NOT NULL DEFAULT 0'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE catalogs('
            . 'uid TEXT PRIMARY KEY,'
            . 'sort_order INTEGER NOT NULL,'
            . 'slug TEXT NOT NULL,'
            . 'file TEXT NOT NULL,'
            . 'name TEXT NOT NULL,'
            . 'description TEXT,'
            . 'event_uid TEXT,'
            . 'raetsel_buchstabe TEXT,'
            . 'comment TEXT,'
            . 'design_path TEXT'
            . ')'
        );
        $pdo->exec(
            "INSERT INTO events(uid,slug,name) VALUES('e1','e1','Event')"
        );
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('c1',1,'s1','s1.json','Cat1','e1')"
        );
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('c2',2,'s2','s2.json','Cat2','e1')"
        );
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('c3',3,'s3','s3.json','Cat3','e1')"
        );

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

    public function testCatalogsEndpointFallsBackToLegacyPayloadOnDatabaseError(): void {
        $service = $this->createMock(CatalogService::class);
        $service->expects($this->once())
            ->method('fetchPagedCatalogs')
            ->willThrowException(new PDOException('database error'));
        $service->expects($this->never())
            ->method('countCatalogs');

        $controller = new AdminCatalogController($service);

        $request = $this->createRequest('GET', '/admin/catalogs/data');
        $response = $controller->catalogs($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame(['useLegacy' => true], $data);
    }
}
