<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

/**
 * Provides endpoints for listing, restoring, downloading and deleting backups.
 */
class BackupController
{
    private string $dir;

    private ?ImportController $importController;

    /**
     * Configure backup directory and optional import controller.
     */
    public function __construct(string $dir, ?ImportController $importController = null) {
        $this->dir = rtrim($dir, '/');
        $this->importController = $importController;
    }

    /**
     * Render the backup table rows server-side.
     */
    public function index(Request $request, Response $response): Response {
        if (!is_dir($this->dir)) {
            $response->getBody()->write(json_encode([
                'error' => 'Backup directory not found',
            ]));

            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        if (!is_readable($this->dir)) {
            $response->getBody()->write(json_encode([
                'error' => 'Backup directory not readable',
            ]));

            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        $dirs = glob($this->dir . '/*', GLOB_ONLYDIR) ?: [];
        rsort($dirs);
        $names = array_map('basename', $dirs);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/_backup_table.twig', [
            'backups' => $names,
        ]);
    }

    /**
     * Restore a backup by delegating to the ImportController.
     */
    public function restore(Request $request, Response $response, array $args): Response {
        if ($this->importController === null) {
            return $response->withStatus(500);
        }
        return $this->importController->import($request, $response, $args);
    }

    /**
     * Create a ZIP archive of the requested backup and return it for download.
     */
    public function download(Request $request, Response $response, array $args): Response {
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

        $size = filesize($zipFile);
        $stream = fopen($zipFile, 'rb');
        if ($stream === false) {
            if (!unlink($zipFile)) {
                return $this->deleteError($response, $zipFile, false);
            }

            return $response->withStatus(500);
        }

        while (!feof($stream)) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                fclose($stream);
                if (!unlink($zipFile)) {
                    return $this->deleteError($response, $zipFile, false);
                }

                return $response->withStatus(500);
            }
            $response->getBody()->write($chunk);
        }

        fclose($stream);

        if (!unlink($zipFile)) {
            return $this->deleteError($response, $zipFile, false);
        }

        return $response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Length', (string) $size)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '.zip"');
    }

    /**
     * Delete the specified backup directory.
     */
    public function delete(Request $request, Response $response, array $args): Response {
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
            $filePath = $file->getPathname();
            if ($file->isDir()) {
                if (!rmdir($filePath)) {
                    return $this->deleteError($response, $filePath, true);
                }
            } else {
                if (!unlink($filePath)) {
                    return $this->deleteError($response, $filePath, false);
                }
            }
        }

        $ok = rmdir($path);
        if (!$ok && is_dir($path)) {
            $status = is_writable($path) && is_writable(dirname($path)) ? 500 : 403;
            $message = $status === 403
                ? 'Permission denied deleting backup directory'
                : 'Failed to delete backup directory';
            $response->getBody()->write(json_encode([
                'error' => $message,
                'path' => $path,
            ]));
            return $response
                ->withStatus($status)
                ->withHeader('Content-Type', 'application/json');
        }

        return $response->withStatus(204);
    }

    private function deleteError(Response $response, string $path, bool $isDir): Response {
        $response->getBody()->write(json_encode([
            'error' => 'Failed to delete ' . ($isDir ? 'directory' : 'file'),
            'path' => $path,
        ]));

        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
}
