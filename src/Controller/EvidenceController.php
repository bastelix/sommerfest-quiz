<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResultService;
use App\Service\PhotoConsentService;
use App\Service\SummaryPhotoService;
use App\Service\ImageUploadService;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Handles upload and retrieval of photo evidence.
 */
class EvidenceController
{
    private ResultService $results;
    private PhotoConsentService $consent;
    private SummaryPhotoService $summary;
    private ImageUploadService $images;
    private LoggerInterface $logger;

    /**
     * Set up controller dependencies.
     */
    public function __construct(
        ResultService $results,
        PhotoConsentService $consent,
        SummaryPhotoService $summary,
        ImageUploadService $images,
        LoggerInterface $logger
    ) {
        $this->results = $results;
        $this->consent = $consent;
        $this->summary = $summary;
        $this->images = $images;
        $this->logger = $logger;
    }

    /**
     * Store an uploaded photo and link it to a result entry.
     */
    public function post(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();
        if (!isset($files['photo'])) {
            $response->getBody()->write('missing file');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $file = $files['photo'];
        try {
            $this->images->validate(
                $file,
                20 * 1024 * 1024,
                ['jpg', 'jpeg', 'png', 'webp'],
                ['image/jpeg', 'image/png', 'image/webp']
            );
        } catch (\RuntimeException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $parsed = $request->getParsedBody() ?? [];
        $user = isset($parsed['name']) ? (string)$parsed['name'] : '';
        $catalog = isset($parsed['catalog']) ? (string)$parsed['catalog'] : '';
        $team = isset($parsed['team']) ? (string)$parsed['team'] : '';
        $rotate = isset($parsed['rotate']) ? (int)$parsed['rotate'] : 0;
        if (!in_array($rotate, [0, 90, 180, 270], true)) {
            $rotate = 0;
        }
        if ($team === '') {
            $response->getBody()->write('missing team');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $safeUser = preg_replace('/[^A-Za-z0-9_-]/', '_', $user);
        $safeCatalog = preg_replace('/[^A-Za-z0-9_-]/', '_', $catalog);
        $date = date('Y-m-d_H-i-s');
        $fileName = $safeCatalog . '_' . $date . '.jpg';
        $img = $this->images->readImage($file);

        try {
            $img->orient();
        } catch (\Throwable $e) {
            $this->logger->warning('Photo rotation failed: ' . $e->getMessage());
        }
        if ($rotate !== 0) {
            $img->rotate(-$rotate);
        }

        $stored = $this->images->saveImage($img, 'photos/' . $safeUser, $fileName, 1500, 1500, 70);

        $this->consent->add($team, time());

        $path = str_replace('/photos/', '/photo/', $stored);
        if ($user !== '' && $catalog === 'summary') {
            $this->summary->add($user, $path, time());
        } elseif ($user !== '' && $catalog !== '') {
            $this->results->setPhoto($user, $catalog, $path);
        }

        $response->getBody()->write(json_encode(['path' => $path]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Serve a stored evidence photo.
     */
    public function get(Request $request, Response $response, array $args = []): Response
    {
        $team = isset($args['team']) ? preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$args['team']) : '';
        $file = basename((string)($args['file'] ?? ''));
        $base = $this->images->getDataDir() . '/photos';
        $path = $base . '/' . $team . '/' . $file;
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

    /**
     * Rotate an existing photo clockwise by 90 degrees.
     */
    public function rotate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $ct = $request->getHeaderLine('Content-Type');
        if (str_starts_with($ct, 'application/json')) {
            $json = json_decode((string) $request->getBody(), true);
            if (is_array($json)) {
                $data = $json;
            }
        }

        $path = isset($data['path']) ? (string)$data['path'] : '';
        if (!preg_match('#^/photo/([^/]+)/([^/]+)$#', $path, $m)) {
            return $response->withStatus(400);
        }
        $team = preg_replace('/[^A-Za-z0-9_-]/', '_', $m[1]);
        $file = basename($m[2]);
        $filePath = $this->images->getDataDir() . '/photos/' . $team . '/' . $file;
        if (!is_file($filePath)) {
            return $response->withStatus(404);
        }
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $createFn = match ($ext) {
            'jpg', 'jpeg' => 'imagecreatefromjpeg',
            'png' => 'imagecreatefrompng',
            'webp' => function ($p) {
                return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($p) : false;
            },
            default => null,
        };

        $saveFn = match ($ext) {
            'jpg', 'jpeg' => fn($img, $p) => imagejpeg($img, $p, 90),
            'png' => fn($img, $p) => imagepng($img, $p),
            'webp' => fn($img, $p) => function_exists('imagewebp') ? imagewebp($img, $p, 90) : false,
            default => null,
        };

        if (!$createFn || !$saveFn) {
            return $response->withStatus(400);
        }

        $src = is_callable($createFn) ? $createFn($filePath) : null;
        if (!$src) {
            return $response->withStatus(400);
        }

        $rot = imagerotate($src, 270, 0);
        imagedestroy($src);
        if (!$rot) {
            return $response->withStatus(500);
        }

        $ok = $saveFn($rot, $filePath);
        imagedestroy($rot);
        if (!$ok) {
            return $response->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
