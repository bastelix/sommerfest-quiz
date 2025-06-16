<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResultService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EvidenceController
{
    private ResultService $results;
    private string $dir;

    public function __construct(ResultService $results, string $dir)
    {
        $this->results = $results;
        $this->dir = rtrim($dir, '/');
    }

    public function post(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();
        if (!isset($files['photo'])) {
            return $response->withStatus(400);
        }
        $file = $files['photo'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $response->withStatus(400);
        }
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $response->withStatus(400);
        }
        $parsed = $request->getParsedBody() ?? [];
        $user = isset($parsed['name']) ? (string)$parsed['name'] : '';
        $catalog = isset($parsed['catalog']) ? (string)$parsed['catalog'] : '';

        $safeUser = preg_replace('/[^A-Za-z0-9_-]/', '_', $user);
        $safeCatalog = preg_replace('/[^A-Za-z0-9_-]/', '_', $catalog);
        $date = date('Y-m-d_H-i-s');
        $fileName = $safeCatalog . '_' . $date . '.' . $ext;

        $dir = $this->dir . '/' . $safeUser;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $target = $dir . '/' . $fileName;
        $file->moveTo($target);

        $path = '/photo/' . rawurlencode($safeUser) . '/' . rawurlencode($fileName);
        if ($user !== '' && $catalog !== '') {
            $this->results->setPhoto($user, $catalog, $path);
        }

        $response->getBody()->write(json_encode(['path' => $path]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args = []): Response
    {
        $team = isset($args['team']) ? preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$args['team']) : '';
        $file = basename((string)($args['file'] ?? ''));
        $path = $this->dir . '/' . $team . '/' . $file;
        if (!is_file($path)) {
            return $response->withStatus(404);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $type = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream'
        };
        $response->getBody()->write((string)file_get_contents($path));
        return $response->withHeader('Content-Type', $type);
    }
}
