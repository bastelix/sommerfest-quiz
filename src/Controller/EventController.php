<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EventService;
use App\Service\NamespaceResolver;
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
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $data = $this->service->getAll($namespace);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function post(Request $request, Response $response): Response {
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $data = json_decode((string)$request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $this->service->saveAll($data, $namespace);
        return $response->withStatus(204);
    }
}
