<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Manages uploading and serving the site logo image.
 */
class LogoController
{
    private ConfigService $config;

    /**
     * Inject configuration service.
     */
    public function __construct(ConfigService $config)
    {
        $this->config = $config;
    }

    /**
     * Return the stored logo image in the requested format.
     */
    public function get(Request $request, Response $response, array $args = []): Response
    {
        $cfg = $this->config->getConfig();
        $relPath = $cfg['logoPath'] ?? '';
        if ($relPath === '') {
            return $response->withStatus(404);
        }
        $path = __DIR__ . '/../../data' . $relPath;
        if (!file_exists($path)) {
            return $response->withStatus(404);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $response->getBody()->write((string)file_get_contents($path));
        $contentType = $ext === 'webp' ? 'image/webp' : 'image/png';
        return $response->withHeader('Content-Type', $contentType);
    }

    /**
     * Upload and store a new logo image.
     */
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

        $uid = $this->config->getActiveEventUid();
        $base = $uid !== '' ? "logo-$uid.$extension" : "logo.$extension";
        $target = __DIR__ . "/../../data/" . $base;
        if (!class_exists('\\Intervention\\Image\\ImageManager')) {
            $response->getBody()->write('Intervention Image NICHT installiert');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }

        $manager = new ImageManager(new Driver());
        $img = $manager->read($file->getStream());
        $img->scaleDown(512, 512);
        $img->save($target, 80);

        $cfg = $this->config->getConfig();
        $cfg['logoPath'] = '/' . $base;
        $this->config->saveConfig($cfg);

        return $response->withStatus(204);
    }
}
