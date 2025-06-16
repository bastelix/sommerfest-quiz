<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LogoController
{
    private ConfigService $config;

    public function __construct(ConfigService $config)
    {
        $this->config = $config;
    }

    public function get(Request $request, Response $response, array $args = []): Response
    {
        $ext = $args['ext'] ?? 'png';
        if (!in_array($ext, ['png', 'webp'], true)) {
            return $response->withStatus(400);
        }

        $path = __DIR__ . "/../../data/logo.$ext";
        if (!file_exists($path)) {
            return $response->withStatus(404);
        }
        $response->getBody()->write((string)file_get_contents($path));
        $contentType = $ext === 'webp' ? 'image/webp' : 'image/png';
        return $response->withHeader('Content-Type', $contentType);
    }

    public function post(Request $request, Response $response): Response
    {
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

        $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'webp'], true)) {
            $response->getBody()->write('unsupported file type');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $target = __DIR__ . "/../../data/logo.$extension";
        $file->moveTo($target);

        $cfg = $this->config->getConfig();
        $cfg['logoPath'] = '/logo.' . $extension;
        $this->config->saveConfig($cfg);

        return $response->withStatus(204);
    }
}
