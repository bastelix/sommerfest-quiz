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
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
        $name = 'photo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $this->dir . '/' . $name;
        $file->moveTo($target);

        $parsed = $request->getParsedBody() ?? [];
        $user = isset($parsed['name']) ? (string)$parsed['name'] : '';
        $catalog = isset($parsed['catalog']) ? (string)$parsed['catalog'] : '';
        $path = '/photo/' . $name;
        if ($user !== '' && $catalog !== '') {
            $this->results->setPhoto($user, $catalog, $path);
        }

        $response->getBody()->write(json_encode(['path' => $path]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args = []): Response
    {
        $file = basename((string)($args['name'] ?? ''));
        $path = $this->dir . '/' . $file;
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
