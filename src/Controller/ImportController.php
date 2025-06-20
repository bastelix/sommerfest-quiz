<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ImportController
{
    private CatalogService $service;
    private string $dataDir;

    public function __construct(CatalogService $service, string $dataDir)
    {
        $this->service = $service;
        $this->dataDir = rtrim($dataDir, '/');
    }

    public function post(Request $request, Response $response): Response
    {
        $catalogDir = $this->dataDir . '/kataloge';
        $catalogsFile = $catalogDir . '/catalogs.json';
        if (!is_readable($catalogsFile)) {
            return $response->withStatus(404);
        }
        $catalogs = json_decode((string)file_get_contents($catalogsFile), true) ?? [];
        $this->service->write('catalogs.json', $catalogs);
        foreach ($catalogs as $cat) {
            if (!isset($cat['file'])) {
                continue;
            }
            $file = basename((string)$cat['file']);
            $path = $catalogDir . '/' . $file;
            if (!is_readable($path)) {
                continue;
            }
            $questions = json_decode((string)file_get_contents($path), true) ?? [];
            $this->service->write($file, $questions);
        }
        return $response->withStatus(204);
    }
}
