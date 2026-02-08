<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EventService;
use App\Service\ConfigService;
use App\Service\ImageUploadService;
use App\Service\ConfigValidator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Provides endpoints to retrieve and update configuration for a specific event.
 */
class EventConfigController
{
    private EventService $events;
    private ConfigService $config;
    private ImageUploadService $images;

    public function __construct(EventService $events, ConfigService $config, ImageUploadService $images) {
        $this->events = $events;
        $this->config = $config;
        $this->images = $images;
    }

    /**
     * Return configuration details for the given event UID.
     */
    public function show(Request $request, Response $response, array $args): Response {
        $uid = (string) ($args['id'] ?? '');
        $event = $this->events->getByUid($uid);
        if ($event === null) {
            return $response->withStatus(404);
        }
        if (!$this->isNamespaceAllowed($request, $event)) {
            return $response->withStatus(403);
        }
        $cfg = $this->config->getConfigForEvent($uid);
        $payload = ['event' => $event, 'config' => $cfg];
        $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update configuration for the specified event UID.
     */
    public function update(Request $request, Response $response, array $args): Response {
        $uid = (string) ($args['id'] ?? '');
        $event = $this->events->getByUid($uid);
        if ($event === null) {
            return $response->withStatus(404);
        }
        if (!$this->isNamespaceAllowed($request, $event)) {
            return $response->withStatus(403);
        }
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $validation = (new ConfigValidator())->validate($data);
        if ($validation['errors'] !== []) {
            $response->getBody()->write(json_encode(['errors' => $validation['errors']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $data = array_merge($data, $validation['config']);

        $files = $request->getUploadedFiles();
        if (isset($files['logo']) && $files['logo']->getError() === UPLOAD_ERR_OK) {
            $file = $files['logo'];
            try {
                $this->images->validate(
                    $file,
                    5 * 1024 * 1024,
                    ['png', 'webp', 'svg'],
                    ['image/png', 'image/webp', 'image/svg+xml']
                );
                $data['logoPath'] = $this->images->saveUploadedFile(
                    $file,
                    'events/' . $uid,
                    'logo',
                    512,
                    512,
                    ImageUploadService::QUALITY_LOGO,
                    true
                );
            } catch (\RuntimeException $e) {
                $response->getBody()->write($e->getMessage());
                return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
            }
        }

        $data['event_uid'] = $uid;
        $this->config->saveConfig($data);
        $cfg = $this->config->getConfigForEvent($uid);
        $payload = ['event' => $event, 'config' => $cfg];
        $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Rotate the public or sponsor dashboard token for the given event.
     */
    public function rotateToken(Request $request, Response $response, array $args): Response {
        $uid = (string) ($args['id'] ?? '');
        $event = $this->events->getByUid($uid);
        if ($event === null) {
            return $response->withStatus(404);
        }
        if (!$this->isNamespaceAllowed($request, $event)) {
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        $variant = is_array($data) ? (string) ($data['variant'] ?? 'public') : 'public';
        $variant = $variant === 'sponsor' ? 'sponsor' : 'public';

        $token = $this->config->generateDashboardToken();
        $this->config->setDashboardToken($uid, $variant, $token);
        $enableKey = $variant === 'sponsor' ? 'dashboardSponsorEnabled' : 'dashboardShareEnabled';
        $this->config->saveConfig([
            'event_uid' => $uid,
            $enableKey => true,
        ]);

        $payload = ['variant' => $variant, 'token' => $token];
        $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $response->getBody()->write($content);

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function isNamespaceAllowed(Request $request, array $event): bool
    {
        $eventNamespace = $request->getAttribute('eventNamespace');
        if (!is_string($eventNamespace) || $eventNamespace === '') {
            return true;
        }
        $eventNs = strtolower(trim($event['namespace'] ?? 'default'));
        return $eventNs === strtolower(trim($eventNamespace));
    }
}
