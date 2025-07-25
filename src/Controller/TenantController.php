<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TenantService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API endpoints for managing tenants.
 */
class TenantController
{
    private TenantService $service;

    public function __construct(TenantService $service)
    {
        $this->service = $service;
    }

    public function create(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data) || !isset($data['uid'], $data['schema'])) {
            return $response->withStatus(400);
        }
        try {
            $this->service->createTenant((string) $data['uid'], (string) $data['schema']);
        } catch (\RuntimeException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }
        return $response->withStatus(201);
    }

    public function delete(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data) || !isset($data['uid'])) {
            return $response->withStatus(400);
        }
        $this->service->deleteTenant((string) $data['uid']);
        return $response->withStatus(204);
    }

    /**
     * Check if a tenant with the given subdomain already exists.
     */
    public function exists(Request $request, Response $response, array $args): Response
    {
        $sub = (string) ($args['subdomain'] ?? '');
        return $this->service->exists($sub)
            ? $response->withStatus(200)
            : $response->withStatus(404);
    }
}
