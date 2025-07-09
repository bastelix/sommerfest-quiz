<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Domain\Roles;
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
        $cfg = $this->service->getConfig();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }
        if ($cfg === []) {
            return $response->withStatus(404);
        }

        $content = json_encode($cfg, JSON_PRETTY_PRINT);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Persist a new configuration payload.
     */
    public function post(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== Roles::ADMIN && $role !== Roles::EVENT_MANAGER) {
            return $response->withStatus(403);
        }
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
