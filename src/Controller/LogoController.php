<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\ImageUploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Manages uploading and serving the site logo image.
 */
class LogoController
{
    private ConfigService $config;
    private ImageUploadService $images;

    /**
     * Inject services.
     */
    public function __construct(ConfigService $config, ImageUploadService $images)
    {
        $this->config = $config;
        $this->images = $images;
    }

    /**
     * Return the stored logo image in the requested format.
     */
    public function get(Request $request, Response $response, array $args = []): Response
    {
        $file = (string)($request->getAttribute('file') ?? '');
        $ext = strtolower((string)($request->getAttribute('ext') ?? 'png'));
        $uid = '';
        if (preg_match('/^logo-([\w-]+)\.' . preg_quote($ext, '/') . '$/', $file, $m)) {
            $uid = $m[1];
        }

        $cfg = $uid !== ''
            ? $this->config->getConfigForEvent($uid)
            : $this->config->getConfig();

        $relPath = $cfg['logoPath'] ?? '';
        $path = '';
        $contentType = 'image/png';

        if ($relPath !== '') {
            $path = __DIR__ . '/../../data' . $relPath;
            if (file_exists($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $contentType = $ext === 'webp' ? 'image/webp' : 'image/png';
            } else {
                $path = '';
            }
        }

        // Fallback to the public favicon when no custom logo is available.
        if ($path === '') {
            $path = __DIR__ . '/../../public/favicon.svg';
            $contentType = 'image/svg+xml';
        }

        $response->getBody()->write((string)file_get_contents($path));
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

        try {
            $this->images->validate(
                $file,
                5 * 1024 * 1024,
                ['png', 'webp'],
                ['image/png', 'image/webp']
            );
        } catch (\RuntimeException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $uid = $this->config->getActiveEventUid();
        $dir = $uid !== '' ? 'events/' . $uid : 'uploads';
        $path = $this->images->saveUploadedFile($file, $dir, 'logo', 512, 512, 80);

        $cfg = $this->config->getConfig();
        $cfg['logoPath'] = $path;
        $this->config->saveConfig($cfg);

        return $response->withStatus(204);
    }
}
