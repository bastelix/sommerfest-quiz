<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResultService;
use App\Service\PhotoConsentService;
use Intervention\Image\ImageManagerStatic as Image;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EvidenceController
{
    private ResultService $results;
    private PhotoConsentService $consent;
    private string $dir;

    public function __construct(ResultService $results, PhotoConsentService $consent, string $dir)
    {
        $this->results = $results;
        $this->consent = $consent;
        $this->dir = rtrim($dir, '/');
    }

    public function post(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();
        if (!isset($files['photo'])) {
            $response->getBody()->write('missing file');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $file = $files['photo'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write('upload error');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $response->getBody()->write('unsupported file type');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $parsed = $request->getParsedBody() ?? [];
        $user = isset($parsed['name']) ? (string)$parsed['name'] : '';
        $catalog = isset($parsed['catalog']) ? (string)$parsed['catalog'] : '';
        $team = isset($parsed['team']) ? (string)$parsed['team'] : '';
        if ($team === '') {
            $response->getBody()->write('missing team');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $safeUser = preg_replace('/[^A-Za-z0-9_-]/', '_', $user);
        $safeCatalog = preg_replace('/[^A-Za-z0-9_-]/', '_', $catalog);
        $date = date('Y-m-d_H-i-s');
        $fileName = $safeCatalog . '_' . $date . '.' . $ext;

        $dir = $this->dir . '/' . $safeUser;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $target = $dir . '/' . $fileName;
        if (!class_exists('\\Intervention\\Image\\ImageManager')) {
            $response->getBody()->write('Intervention Image NICHT installiert');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }

        $img = Image::make($file->getStream());
        $img->resize(1500, 1500, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $img->save($target, 70);

        $this->consent->add($team, time());

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
