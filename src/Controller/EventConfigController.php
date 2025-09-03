<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EventService;
use App\Service\ConfigService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

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
        $data['event_uid'] = $uid;
        $this->config->saveConfig($data);
        $cfg = $this->config->getConfigForEvent($uid);
        $payload = ['event' => $event, 'config' => $cfg];
        $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
