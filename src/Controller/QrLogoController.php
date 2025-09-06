<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Intervention\Image\ImageManager;

/**
 * Handles uploading and serving QR code logo images.
 */
class QrLogoController
{
    private ConfigService $config;

    public function __construct(ConfigService $config)
    {
        $this->config = $config;
    }

    public function get(Request $request, Response $response): Response
    {
        $file = (string)($request->getAttribute('file') ?? '');
        $ext = strtolower((string)($request->getAttribute('ext') ?? 'png'));
        $uid = '';
        if (preg_match('/^qrlogo-([\w-]+)\.' . preg_quote($ext, '/') . '$/', $file, $m)) {
            $uid = $m[1];
        }

        if ($uid === '') {
            $path = __DIR__ . '/../../public/favicon.svg';
            $contentType = 'image/svg+xml';
            $response->getBody()->write((string)file_get_contents($path));
            return $response->withHeader('Content-Type', $contentType);
        }

        $cfg = $this->config->getConfigForEvent($uid);

        $relPath = $cfg['qrLogoPath'] ?? '';
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

        if ($path === '') {
            // fallback to site logo if QR logo missing
            $cfgLogo = $cfg['logoPath'] ?? '';
            if ($cfgLogo !== '') {
                $path = __DIR__ . '/../../data' . $cfgLogo;
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $contentType = $ext === 'webp' ? 'image/webp' : 'image/png';
            }
        }

        if ($path === '' || !file_exists($path)) {
            $path = __DIR__ . '/../../public/favicon.svg';
            $contentType = 'image/svg+xml';
        }

        $response->getBody()->write((string)file_get_contents($path));
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
        if ($file->getSize() !== null && $file->getSize() > 5 * 1024 * 1024) {
            $response->getBody()->write('file too large');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'webp'], true)) {
            $response->getBody()->write('unsupported file type');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $params = $request->getQueryParams();
        $uid = (string)($params['event'] ?? '');
        if ($uid === '') {
            $response->getBody()->write('missing event uid');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $base = "qrlogo-$uid.$extension";
        $target = __DIR__ . "/../../data/" . $base;
        if (!class_exists('\Intervention\Image\ImageManager')) {
            $response->getBody()->write('Intervention Image NICHT installiert');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }

        $manager = extension_loaded('imagick') ? ImageManager::imagick() : ImageManager::gd();
        $stream = $file->getStream();
        $img = $manager->read($stream->detach());
        $img->scaleDown(512, 512);
        $img->save($target, 80);

        $cfg = $this->config->getConfigForEvent($uid);
        $cfg['event_uid'] = $uid;
        $cfg['qrLogoPath'] = '/' . $base;
        $this->config->saveConfig($cfg);

        return $response->withStatus(204);
    }
}
