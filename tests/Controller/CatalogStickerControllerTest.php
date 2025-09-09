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
    public function testPdfWithEmptyParameters(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name, published, sort_order) VALUES('ev1','ev1','Event',1,0)");
        $pdo->exec("INSERT INTO catalogs(uid, sort_order, slug, file, name, description, raetsel_buchstabe, event_uid) VALUES('c1',0,'c1','c1.json','Cat','Desc','A','ev1')");
        $config = new ConfigService($pdo);
        $events = new EventService($pdo);
        $catalogs = new CatalogService($pdo, $config);
        $qr = new class extends QrCodeService {
            public function generateCatalog(array $q, array $cfg = []): array
            {
                $img = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
                return ['mime' => 'image/png', 'body' => $img];
            }
        };
        $controller = new CatalogStickerController($config, $events, $catalogs, $qr);
        $request = $this->createRequest('GET', '/catalog-sticker.pdf')
            ->withQueryParams([
                'event_uid' => 'ev1',
                'desc_width' => '',
                'desc_height' => '',
                'desc_top' => '',
                'desc_left' => '',
                'qr_top' => '',
                'qr_left' => '',
                'qr_size_pct' => '',
            ]);
        $response = $controller->pdf($request, new Response());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $body = (string)$response->getBody();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertGreaterThan(0, strlen($body));
    }

    public function testPdfWithValidParameters(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name, published, sort_order) VALUES('ev1','ev1','Event',1,0)");
        $pdo->exec("INSERT INTO catalogs(uid, sort_order, slug, file, name, description, raetsel_buchstabe, event_uid) VALUES('c1',0,'c1','c1.json','Cat','Desc','A','ev1')");
        $config = new ConfigService($pdo);
        $events = new EventService($pdo);
        $catalogs = new CatalogService($pdo, $config);
        $qr = new class extends QrCodeService {
            public function generateCatalog(array $q, array $cfg = []): array
            {
                $img = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
                return ['mime' => 'image/png', 'body' => $img];
            }
        };
        $controller = new CatalogStickerController($config, $events, $catalogs, $qr);
        $request = $this->createRequest('GET', '/catalog-sticker.pdf')
            ->withQueryParams([
                'event_uid' => 'ev1',
                'desc_width' => '0.5',
                'desc_height' => '0.4',
                'desc_top' => '0.1',
                'desc_left' => '0.2',
                'qr_top' => '0.3',
                'qr_left' => '0.4',
                'qr_size_pct' => '50',
            ]);
        $response = $controller->pdf($request, new Response());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $body = (string)$response->getBody();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertGreaterThan(0, strlen($body));
    }
}
