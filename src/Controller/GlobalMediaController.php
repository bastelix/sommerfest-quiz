<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\LogService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

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

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $response->getBody()->write((string) file_get_contents($path));

        return $response->withHeader('Content-Type', $mime);
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
