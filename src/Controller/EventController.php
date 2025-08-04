<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EventService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API endpoints for managing events.
 */
class EventController
{
    private EventService $service;

    public function __construct(EventService $service)
    {
        $this->service = $service;
    }

    public function get(Request $request, Response $response): Response
    {
        $data = $this->service->getAll();
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function post(Request $request, Response $response): Response
    {
        $data = json_decode((string)$request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $this->service->saveAll($data);
        return $response->withStatus(204);
    }

    /**
     * Set the published state of an event.
     */
    public function publish(Request $request, Response $response, array $args): Response
    {
        $data = json_decode((string)$request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $uid = (string)($args['uid'] ?? '');
        $published = (bool)($data['published'] ?? false);
        $this->service->setPublished($uid, $published);
        return $response->withStatus(204);
    }
}
