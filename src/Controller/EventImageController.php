<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Serves event-specific uploaded images.
 */
class EventImageController
{
    private ConfigService $config;

    public function __construct(ConfigService $config)
    {
        $this->config = $config;
    }

    /**
     * Return an image from the event's image directory.
     */
    public function get(Request $request, Response $response): Response
    {
        $uid = (string) $request->getAttribute('uid');
        $file = (string) $request->getAttribute('file');
        $uid = preg_replace('~[^a-z0-9\-]~i', '', $uid);
        $file = basename($file);
        if ($uid === '' || $file === '') {
            return $response->withStatus(404);
        }

        $this->config->migrateEventImages($uid);
        $dir = $this->config->getEventImagesDir($uid);
        $path = $dir . '/' . $file;
        if (!is_file($path)) {
            return $response->withStatus(404);
        }
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $response->getBody()->write((string) file_get_contents($path));
        return $response->withHeader('Content-Type', $mime);
    }
}
