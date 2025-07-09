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
        $this->service->createTenant((string) $data['uid'], (string) $data['schema']);
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
}
