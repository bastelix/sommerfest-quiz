<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    private string $catalogPath;
    private string $teamsPath;
    private string $catalogBackup;
    private string $teamsBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogPath = __DIR__ . '/../../data/kataloge/catalogs.json';
        $this->teamsPath = __DIR__ . '/../../data/teams.json';
        $this->catalogBackup = file_get_contents($this->catalogPath);
        $this->teamsBackup = file_exists($this->teamsPath) ? file_get_contents($this->teamsPath) : '';
    }

    protected function tearDown(): void
    {
        file_put_contents($this->catalogPath, $this->catalogBackup);
        file_put_contents($this->teamsPath, $this->teamsBackup);
        parent::tearDown();
    }

    public function testExportPdf(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/export.pdf');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->getHeaderLine('Content-Type'));
    }

    public function testExportPdfWithQrImages(): void
    {
        $img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQI12NgYGBgAAAABAABJzQnKgAAAABJRU5ErkJggg==';

        $catalogs = json_decode($this->catalogBackup, true);
        $catalogs[0]['qr_image'] = $img;
        file_put_contents($this->catalogPath, json_encode($catalogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $teams = $this->teamsBackup !== '' ? json_decode($this->teamsBackup, true) : [];
        if (!is_array($teams)) {
            $teams = [];
        }
        $teams[] = ['name' => 'Test', 'qr_image' => $img];
        file_put_contents($this->teamsPath, json_encode($teams, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/export.pdf');
        $response = $app->handle($request);
        $content = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('PNG', $content);
    }

    public function testExportHtml(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/export.html');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('export-card', (string) $response->getBody());
    }
}
