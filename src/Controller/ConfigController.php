<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API endpoints for reading and updating application configuration.
 */
class ConfigController
{
    private ConfigService $service;

    /**
     * Inject configuration service dependency.
     */
    public function __construct(ConfigService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the current configuration as JSON.
     */
    public function get(Request $request, Response $response): Response
    {
        $content = $this->service->getJson();
        if ($content === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Persist a new configuration payload.
     */
    public function post(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
            if (!is_array($data)) {
                return $response->withStatus(400);
            }
        } elseif (!is_array($data)) {
            return $response->withStatus(400);
        }

        $this->service->saveConfig($data);

        return $response->withStatus(204);
    }
}
