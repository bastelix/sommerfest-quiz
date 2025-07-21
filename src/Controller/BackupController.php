<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Provides endpoints for listing, downloading and deleting backups.
 */
class BackupController
{
    private string $dir;

    /**
     * Set the backup directory path.
     */
    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
    }

    /**
     * Return a JSON list of available backups.
     */
    public function list(Request $request, Response $response): Response
    {
        $dirs = glob($this->dir . '/*', GLOB_ONLYDIR) ?: [];
        rsort($dirs);
        $names = array_map('basename', $dirs);
        $response->getBody()->write(json_encode($names, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a ZIP archive of the requested backup and return it for download.
     */
    public function download(Request $request, Response $response, array $args): Response
    {
        $name = basename((string)($args['name'] ?? ''));
        $path = $this->dir . '/' . $name;
        if (!is_dir($path)) {
            return $response->withStatus(404);
        }
        $zipFile = sys_get_temp_dir() . '/' . $name . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            return $response->withStatus(500);
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($path) + 1));
        }
        $zip->close();

        $data = file_get_contents($zipFile);
        if ($data === false) {
            @unlink($zipFile);
            return $response->withStatus(500);
        }

        $size = filesize($zipFile);
        @unlink($zipFile);

        $response->getBody()->write($data);
        return $response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Length', (string) $size)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '.zip"');
    }

    /**
     * Delete the specified backup directory.
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $name = basename((string)($args['name'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name) || $name === '.' || $name === '..') {
            return $response->withStatus(400);
        }
        $path = $this->dir . '/' . $name;
        if (!is_dir($path)) {
            return $response->withStatus(404);
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        $ok = rmdir($path);
        if (!$ok && is_dir($path)) {
            $status = is_writable($path) && is_writable(dirname($path)) ? 500 : 403;
            $message = $status === 403
                ? 'Permission denied deleting backup directory'
                : 'Failed to delete backup directory';
            $response->getBody()->write(json_encode(['error' => $message]));
            return $response
                ->withStatus($status)
                ->withHeader('Content-Type', 'application/json');
        }

        return $response->withStatus(204);
    }
}
