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
    public function __construct(ConfigService $config, ?ImageUploadService $images = null) {
        $this->config = $config;
        $this->images = $images ?? new ImageUploadService(sys_get_temp_dir());
    }

    /**
     * Return the stored logo image in the requested format.
     */
    public function get(Request $request, Response $response, array $args = []): Response {
        $file = (string)($request->getAttribute('file') ?? '');
        $ext = strtolower((string)($request->getAttribute('ext') ?? 'png'));
        $uid = '';
        if (preg_match('/^logo-([\w-]+)\.' . preg_quote($ext, '/') . '$/', $file, $m)) {
            $uid = $m[1];
        } else {
            $uid = $this->config->getActiveEventUid();
        }

        $this->config->migrateEventImages($uid);
        $candidates = ['png', 'webp', 'svg'];
        $searchOrder = array_values(array_unique(array_merge([$ext], array_diff($candidates, [$ext]))));
        $path = null;
        $directories = [];
        if ($uid !== '') {
            $directories[] = $this->config->getEventImagesDir($uid);
        }
        $directories[] = $this->config->getGlobalUploadsDir();

        foreach ($directories as $dir) {
            foreach ($searchOrder as $candidate) {
                $candidatePath = rtrim($dir, '/') . '/logo.' . $candidate;
                if (is_file($candidatePath)) {
                    $path = $candidatePath;
                    $ext = $candidate;
                    break 2;
                }
            }
        }
        if ($path === null) {
            $path = __DIR__ . '/../../public/favicon.svg';
            $ext = 'svg';
        }
        $contentType = match ($ext) {
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
        $response->getBody()->write((string)file_get_contents($path));
        return $response->withHeader('Content-Type', $contentType);
    }

    /**
     * Upload and store a new logo image.
     */
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
                ['png', 'webp', 'svg'],
                ['image/png', 'image/webp', 'image/svg+xml']
            );
        } catch (\RuntimeException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $uid = $this->config->getActiveEventUid();
        $dir = $uid !== '' ? 'events/' . $uid . '/images' : 'uploads';
        $path = $this->images->saveUploadedFile(
            $file,
            $dir,
            'logo',
            512,
            512,
            ImageUploadService::QUALITY_LOGO,
            true
        );

        if ($uid !== '') {
            $cfg = $this->config->getConfig();
            $cfg['logoPath'] = $path;
            $this->config->saveConfig($cfg);
        }

        return $response->withStatus(204);
    }
}
