<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\ConfigService;
use Tests\TestCase;

class EvidenceControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('MAIN_DOMAIN=localhost');
        $_ENV['MAIN_DOMAIN'] = 'localhost';
    }

    protected function tearDown(): void
    {
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
        parent::tearDown();
    }

    public function testServesEvidencePhotoWithCaching(): void
    {
        $pdo = $this->getDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name) VALUES('ev1','ev1','Event')");
        $config = new ConfigService($pdo);
        $config->setActiveEventUid('ev1');

        $dir = __DIR__ . '/../../data/events/ev1/images/photos/team';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/evidence.png';
        imagepng(imagecreatetruecolor(3, 3), $file);

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/photo/team/evidence.png');
        $request = $request->withUri(new \Slim\Psr7\Uri('http', 'localhost', 80, '/photo/team/evidence.png'));
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));

        $expectedEtag = '"' . hash_file('sha256', $file) . '"';
        $this->assertSame($expectedEtag, $response->getHeaderLine('ETag'));
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $expectedEtag);

        $mtime = filemtime($file) ?: 0;
        $this->assertSame(
            gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            $response->getHeaderLine('Last-Modified')
        );

        $this->cleanupEvidence($file);
    }

    public function testEvidenceConditionalRequest(): void
    {
        $pdo = $this->getDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name) VALUES('ev2','ev2','Event')");
        $config = new ConfigService($pdo);
        $config->setActiveEventUid('ev2');

        $dir = __DIR__ . '/../../data/events/ev2/images/photos/team';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/evidence.png';
        imagepng(imagecreatetruecolor(3, 3), $file);

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/photo/team/evidence.png');
        $request = $request->withUri(new \Slim\Psr7\Uri('http', 'localhost', 80, '/photo/team/evidence.png'));
        $initial = $app->handle($request);

        $etag = $initial->getHeaderLine('ETag');
        $this->assertNotSame('', $etag);

        $conditional = $this->createRequest('GET', '/photo/team/evidence.png')
            ->withUri(new \Slim\Psr7\Uri('http', 'localhost', 80, '/photo/team/evidence.png'))
            ->withHeader('If-None-Match', $etag);
        $response = $app->handle($conditional);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeaderLine('Cache-Control'));
        $this->assertSame($etag, $response->getHeaderLine('ETag'));

        $this->cleanupEvidence($file);
    }

    private function cleanupEvidence(string $file): void
    {
        if (is_file($file)) {
            unlink($file);
        }
        $teamDir = dirname($file);
        if (is_dir($teamDir)) {
            rmdir($teamDir);
        }
        $photosDir = dirname($teamDir);
        if (is_dir($photosDir)) {
            rmdir($photosDir);
        }
        $imagesDir = dirname($photosDir);
        if (is_dir($imagesDir)) {
            rmdir($imagesDir);
        }
        $eventDir = dirname($imagesDir);
        if (is_dir($eventDir)) {
            rmdir($eventDir);
        }
    }
}
