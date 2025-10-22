<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Support\HttpCacheHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Stream;

/**
 * Serves event-specific uploaded images.
 */
class EventImageController
{
    private ConfigService $config;

    public function __construct(ConfigService $config) {
        $this->config = $config;
    }

    /**
     * Return an image from the event's image directory.
     */
    public function get(Request $request, Response $response): Response {
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
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return $response->withStatus(500);
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $stream = new Stream($handle);
        $response = $response->withBody($stream)->withHeader('Content-Type', $mime);

        $etag = '"' . hash_file('sha256', $path) . '"';
        $lastModified = filemtime($path) ?: time();

        return HttpCacheHelper::apply(
            $request,
            $response,
            'public, max-age=31536000, immutable',
            $etag,
            $lastModified
        );
    }
}
