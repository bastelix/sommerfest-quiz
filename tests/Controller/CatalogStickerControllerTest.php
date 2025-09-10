<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\CatalogStickerController;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\ImageUploadService;
use App\Service\QrCodeService;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;
use Slim\Psr7\UploadedFile;
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
                'desc_width' => '50',
                'desc_height' => '40',
                'desc_top' => '10',
                'desc_left' => '20',
                'qr_top' => '30',
                'qr_left' => '40',
                'qr_size_pct' => '50',
            ]);
        $response = $controller->pdf($request, new Response());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $body = (string)$response->getBody();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertGreaterThan(0, strlen($body));
    }

    public function testPdfWithAdditionalTemplate(): void
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
                'template' => 'avery_l7651',
            ]);
        $response = $controller->pdf($request, new Response());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
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

        $bg = __DIR__ . '/../../data/events/ev1/images/sticker-bg.png';
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

    public function testGetSettingsProvidesPreviewFields(): void
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
        $this->assertSame('EventTitle', $data['previewHeader']);
        $this->assertSame('EventDesc', $data['previewSubheader']);
        $this->assertSame('CatName', $data['previewCatalog']);
        $this->assertSame('CatDesc', $data['previewDesc']);
        $this->assertArrayNotHasKey('previewText', $data);
        $this->assertFalse($data['stickerPrintHeader']);
        $this->assertFalse($data['stickerPrintSubheader']);
        $this->assertFalse($data['stickerPrintCatalog']);
    }

    public function testUploadBackgroundStoresImageAndConfig(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name, published, sort_order) VALUES('ev1','ev1','Event',1,0)");
        $config = new ConfigService($pdo);
        $events = new EventService($pdo);
        $catalogs = new CatalogService($pdo, $config);
        $qr = new QrCodeService();
        $images = new ImageUploadService(sys_get_temp_dir());
        $controller = new CatalogStickerController($config, $events, $catalogs, $qr, $images);

        $filePath = sys_get_temp_dir() . '/bg.png';
        $img = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC');
        file_put_contents($filePath, $img);
        $stream = fopen($filePath, 'rb');
        $uploaded = new UploadedFile(new Stream($stream), 'bg.png', 'image/png', filesize($filePath), UPLOAD_ERR_OK);

        $request = $this->createRequest('POST', '/admin/sticker-background')
            ->withUploadedFiles(['file' => $uploaded])
            ->withQueryParams(['event_uid' => 'ev1']);
        $response = $controller->uploadBackground($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $expected = '/events/ev1/images/sticker-bg.png';
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame($expected, $payload['stickerBgPath']);
        $cfg = $config->getConfigForEvent('ev1');
        $this->assertSame($expected, $cfg['stickerBgPath']);
        $this->assertFileExists(sys_get_temp_dir() . $expected);

        unlink(sys_get_temp_dir() . $expected);
        unlink($filePath);
    }
}
