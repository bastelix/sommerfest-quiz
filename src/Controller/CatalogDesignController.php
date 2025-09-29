<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Manage upload and retrieval of catalog design templates.
 */
class CatalogDesignController
{
    private CatalogService $catalogs;

    public function __construct(CatalogService $catalogs) {
        $this->catalogs = $catalogs;
    }

    public function get(Request $request, Response $response): Response {
        $slug = (string)$request->getAttribute('slug');
        $path = $this->catalogs->getDesignPath($slug);
        if ($path === null || $path === '') {
            return $response->withStatus(404);
        }
        $abs = __DIR__ . '/../../data/' . ltrim($path, '/');
        if (!is_readable($abs)) {
            return $response->withStatus(404);
        }
        $response->getBody()->write((string)file_get_contents($abs));
        return $response->withHeader('Content-Type', 'application/pdf');
    }

    public function post(Request $request, Response $response): Response {
        $slug = (string)$request->getAttribute('slug');
        $files = $request->getUploadedFiles();
        if (!isset($files['file'])) {
            $response->getBody()->write('missing file');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $file = $files['file'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write('upload error');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $response->getBody()->write('unsupported file type');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $dir = __DIR__ . '/../../data/designs';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $target = $dir . '/' . $slug . '.pdf';
        $file->moveTo($target);
        $relPath = '/designs/' . $slug . '.pdf';
        $this->catalogs->setDesignPath($slug, $relPath);
        return $response->withStatus(204);
    }
}
