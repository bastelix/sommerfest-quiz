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

    public function testPdfUsesDefaultTemplateL7163(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name, published, sort_order) VALUES('ev1','ev1','Event',1,0)");
        for ($i = 0; $i < 14; $i++) {
            $uid = 'c' . $i;
            $pdo->exec(
                "INSERT INTO catalogs(uid, sort_order, slug, file, name, description, raetsel_buchstabe, event_uid) " .
                "VALUES('{$uid}',{$i},'{$uid}','{$uid}.json','Cat{$i}','Desc{$i}','A','ev1')"
            );
        }
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
            ->withQueryParams(['event_uid' => 'ev1']);
        $response = $controller->pdf($request, new Response());
        $body = (string) $response->getBody();
        $this->assertSame(1, preg_match_all('/\/Type \/Page\b/', $body));
    }

    public function testPdfEmbedsBackgroundImage(): void
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
            ->withQueryParams(['event_uid' => 'ev1']);

        $bg = __DIR__ . '/../../data/uploads/sticker-bg.png';
        @unlink($bg);
        $noBg = (string) $controller->pdf($request, new Response())->getBody();

        $dir = dirname($bg);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $img = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DAwMDAxMDAwMDAAAANHQEDasKb6QAAAABJRU5ErkJggg==');
        file_put_contents($bg, $img);
        try {
            $responseWithBg = $controller->pdf($request, new Response());
        } finally {
            unlink($bg);
        }
        $withBg = (string) $responseWithBg->getBody();
        $this->assertSame('application/pdf', $responseWithBg->getHeaderLine('Content-Type'));
        $this->assertSame(0, preg_match_all('/\/Subtype\s*\/Image\b/', $noBg));
        $this->assertSame(1, preg_match_all('/\/Subtype\s*\/Image\b/', $withBg));
    }

    public function testGetSettingsProvidesPreviewText(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name, description, published, sort_order) VALUES('ev1','ev1','EventTitle','EventDesc',1,0)");
        $pdo->exec("INSERT INTO catalogs(uid, sort_order, slug, file, name, description, raetsel_buchstabe, event_uid) VALUES('c1',0,'c1','c1.json','CatName','CatDesc','A','ev1')");
        $config = new ConfigService($pdo);
        $config->saveConfig([
            'event_uid' => 'ev1',
            'stickerPrintHeader' => false,
            'stickerPrintSubheader' => false,
            'stickerPrintCatalog' => false,
            'stickerPrintDesc' => true,
        ]);
        $events = new EventService($pdo);
        $catalogs = new CatalogService($pdo, $config);
        $qr = new QrCodeService();
        $controller = new CatalogStickerController($config, $events, $catalogs, $qr);
        $request = $this->createRequest('GET', '/admin/sticker-settings')
            ->withQueryParams(['event_uid' => 'ev1']);
        $response = $controller->getSettings($request, new Response());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode((string)$response->getBody(), true);
        $this->assertSame("EventTitle\nEventDesc\nCatName\nCatDesc", $data['previewText']);
        $this->assertFalse($data['stickerPrintHeader']);
        $this->assertFalse($data['stickerPrintSubheader']);
        $this->assertFalse($data['stickerPrintCatalog']);

        $config->saveConfig([
            'event_uid' => 'ev1',
            'stickerPrintHeader' => false,
            'stickerPrintSubheader' => false,
            'stickerPrintCatalog' => false,
            'stickerPrintDesc' => false,
        ]);
        $response = $controller->getSettings($request, new Response());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertSame("EventTitle\nEventDesc\nCatName", $data['previewText']);
    }
}
