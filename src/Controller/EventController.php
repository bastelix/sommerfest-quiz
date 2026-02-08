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

    public function __construct(EventService $service) {
        $this->service = $service;
    }

    public function get(Request $request, Response $response): Response {
        $namespace = $this->resolveNamespace($request);
        $data = $this->service->getAll($namespace);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function post(Request $request, Response $response): Response {
        $data = json_decode((string)$request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $namespace = $this->resolveNamespace($request);
        $this->service->saveAll($data, $namespace);
        return $response->withStatus(204);
    }

    private function resolveNamespace(Request $request): ?string
    {
        $namespace = $request->getAttribute('eventNamespace');
        if (is_string($namespace) && $namespace !== '') {
            return $namespace;
        }
        $namespace = $request->getAttribute('pageNamespace');
        if (is_string($namespace) && $namespace !== '') {
            return $namespace;
        }
        return null;
    }
}
