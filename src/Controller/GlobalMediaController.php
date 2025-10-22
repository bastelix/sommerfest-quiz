<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\LogService;
use App\Support\HttpCacheHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Stream;

class GlobalMediaController
{
    public function __construct(private ConfigService $config, private ?LoggerInterface $logger = null) {
    }

    public function get(Request $request, Response $response): Response {
        $file = (string) $request->getAttribute('file');
        $file = basename($file);
        if ($file === '') {
            return $response->withStatus(404);
        }

        $dir = $this->config->getGlobalUploadsDir();
        $path = $dir . '/' . $file;
        if (!is_file($path)) {
            $this->logMissingFile($file, $path);
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

    private function logMissingFile(string $file, string $path): void
    {
        $logger = $this->logger;
        if ($logger === null) {
            try {
                $logger = LogService::create('uploads');
                $this->logger = $logger;
            } catch (\Throwable $exception) {
                return;
            }
        }

        $logger->notice('Global media fallback could not locate an upload', [
            'file' => $file,
            'path' => $path,
        ]);
    }
}
