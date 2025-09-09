<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\CatalogStickerController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\QrCodeService;
use Slim\Psr7\Response;
use Tests\TestCase;

class CatalogStickerControllerTest extends TestCase
{
    public function testSaveAndGetRelativeCoordinates(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name, published, sort_order) VALUES('ev1','ev1','Event',1,0)");
        $config = new ConfigService($pdo);
        $events = new EventService($pdo);
        $catalogs = new CatalogService($pdo, $config);
        $qr = new QrCodeService();
        $controller = new CatalogStickerController($config, $events, $catalogs, $qr);

        $request = $this->createRequest('POST', '/admin/sticker-settings');
        $request = $request->withParsedBody([
            'event_uid' => 'ev1',
            'stickerDescTop' => 0.25,
            'stickerDescLeft' => 0.33,
            'stickerQrTop' => 0.5,
            'stickerQrLeft' => 0.4,
            'stickerDescWidth' => 0.5,
            'stickerDescHeight' => 0.4,
        ]);
        $response = $controller->saveSettings($request, new Response());
        $this->assertEquals(204, $response->getStatusCode());

        $getReq = $this->createRequest('GET', '/admin/sticker-settings?event_uid=ev1');
        $getRes = $controller->getSettings($getReq, new Response());
        $data = json_decode((string)$getRes->getBody(), true);
        $this->assertSame(0.25, $data['stickerDescTop']);
        $this->assertSame(0.33, $data['stickerDescLeft']);
        $this->assertSame(0.5, $data['stickerQrTop']);
        $this->assertSame(0.4, $data['stickerQrLeft']);
        $this->assertSame(0.5, $data['stickerDescWidth']);
        $this->assertSame(0.4, $data['stickerDescHeight']);
    }
}
