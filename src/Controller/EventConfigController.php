<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EventService;
use App\Service\ConfigService;
use App\Service\ConfigValidator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Intervention\Image\ImageManager;

/**
 * Provides endpoints to retrieve and update configuration for a specific event.
 */
class EventConfigController
{
    private EventService $events;
    private ConfigService $config;

    public function __construct(EventService $events, ConfigService $config)
    {
        $this->events = $events;
        $this->config = $config;
    }

    /**
     * Return configuration details for the given event UID.
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $uid = (string) ($args['id'] ?? '');
        $event = $this->events->getByUid($uid);
        if ($event === null) {
            return $response->withStatus(404);
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
    public function update(Request $request, Response $response, array $args): Response
    {
        $uid = (string) ($args['id'] ?? '');
        $event = $this->events->getByUid($uid);
        if ($event === null) {
            return $response->withStatus(404);
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
            if ($file->getSize() === null || $file->getSize() <= 5 * 1024 * 1024) {
                $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                if (
                    in_array($extension, ['png', 'webp'], true) &&
                    class_exists('\\Intervention\\Image\\ImageManager')
                ) {
                    $base = "logo-$uid.$extension";
                    $target = __DIR__ . "/../../data/" . $base;
                    $manager = extension_loaded('imagick') ? ImageManager::imagick() : ImageManager::gd();
                    $stream = $file->getStream();
                    $img = $manager->read($stream->detach());
                    $img->scaleDown(512, 512);
                    $img->save($target, 80);
                    $data['logoPath'] = '/' . $base;
                }
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
}
