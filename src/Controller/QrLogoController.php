<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\ImageUploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Handles uploading and serving QR code logo images.
 */
class QrLogoController
{
    private ConfigService $config;
    private ImageUploadService $images;

    public function __construct(ConfigService $config, ?ImageUploadService $images = null) {
        $this->config = $config;
        $this->images = $images ?? new ImageUploadService(sys_get_temp_dir());
    }

    public function get(Request $request, Response $response): Response {
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

        $this->config->migrateEventImages($uid);
        $dir = $this->config->getEventImagesDir($uid);
        $path = $dir . '/qrlogo.' . $ext;
        if (!is_file($path)) {
            $alt = $dir . '/qrlogo.' . ($ext === 'png' ? 'webp' : 'png');
            if (is_file($alt)) {
                $path = $alt;
                $ext = strtolower(pathinfo($alt, PATHINFO_EXTENSION));
            } else {
                $path = $dir . '/logo.' . $ext;
                if (!is_file($path)) {
                    $altLogo = $dir . '/logo.' . ($ext === 'png' ? 'webp' : 'png');
                    if (is_file($altLogo)) {
                        $path = $altLogo;
                        $ext = strtolower(pathinfo($altLogo, PATHINFO_EXTENSION));
                    } else {
                        $path = __DIR__ . '/../../public/favicon.svg';
                        $ext = 'svg';
                    }
                }
            }
        }

        $contentType = match ($ext) {
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        $response->getBody()->write((string)file_get_contents($path));
        return $response->withHeader('Content-Type', $contentType);
    }

    public function post(Request $request, Response $response): Response {
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

        $params = $request->getQueryParams();
        $uid = (string)($params['event'] ?? '');
        if ($uid === '') {
            $response->getBody()->write('missing event uid');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $path = $this->images->saveUploadedFile(
            $file,
            'events/' . $uid . '/images',
            'qrlogo',
            512,
            512,
            ImageUploadService::QUALITY_LOGO,
            true
        );

        $cfg = $this->config->getConfigForEvent($uid);
        $cfg['event_uid'] = $uid;
        $cfg['qrLogoPath'] = $path;
        $this->config->saveConfig($cfg);

        return $response->withStatus(204);
    }
}
