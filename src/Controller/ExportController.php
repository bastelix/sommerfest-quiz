<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\PdfExportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ExportController
{
    private ConfigService $config;
    private CatalogService $catalogs;
    private PdfExportService $pdf;

    public function __construct(ConfigService $config, CatalogService $catalogs, PdfExportService $pdf)
    {
        $this->config = $config;
        $this->catalogs = $catalogs;
        $this->pdf = $pdf;
    }

    public function download(Request $request, Response $response): Response
    {
        $cfg = $this->config->getConfig();
        $catJson = $this->catalogs->read('catalogs.json');
        $cats = [];
        if ($catJson !== null) {
            $cats = json_decode($catJson, true) ?? [];
        }
        $content = $this->pdf->build($cfg, $cats);
        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="export.pdf"');
    }
}
