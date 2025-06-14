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

    public function get(Request $request, Response $response): Response
    {
        $path = __DIR__ . '/../../data/logo.png';
        if (!file_exists($path)) {
            return $response->withStatus(404);
        }
        $response->getBody()->write((string)file_get_contents($path));
        return $response->withHeader('Content-Type', 'image/png');
    }

    public function post(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();
        if (!isset($files['file'])) {
            return $response->withStatus(400);
        }
        $file = $files['file'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $response->withStatus(400);
        }
        $target = __DIR__ . '/../../data/logo.png';
        $file->moveTo($target);

        $cfg = $this->config->getConfig();
        $cfg['logoPath'] = '/logo.png';
        $this->config->saveConfig($cfg);

        return $response->withStatus(204);
    }
}
