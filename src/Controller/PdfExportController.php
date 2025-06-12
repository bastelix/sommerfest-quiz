<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Service\TeamService;
use App\Service\PdfExportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PdfExportController
{
    private CatalogService $catalogs;
    private TeamService $teams;
    private PdfExportService $pdf;

    public function __construct(CatalogService $catalogs, TeamService $teams, PdfExportService $pdf)
    {
        $this->catalogs = $catalogs;
        $this->teams = $teams;
        $this->pdf = $pdf;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $catalogsJson = $this->catalogs->read('catalogs.json');
        $list = $catalogsJson ? json_decode($catalogsJson, true) : [];
        $teams = $this->teams->getAll();
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        if ($uri->getPort()) {
            $baseUrl .= ':' . $uri->getPort();
        }
        $data = $this->pdf->build($list, $teams, $baseUrl);
        $response->getBody()->write($data);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="qrcodes.pdf"');
    }
}

