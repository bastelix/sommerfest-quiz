<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SettingsService;
use App\Domain\Roles;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API endpoints for application settings.
 */
class SettingsController
{
    private SettingsService $service;

    public function __construct(SettingsService $service) {
        $this->service = $service;
    }

    public function get(Request $request, Response $response): Response {
        $settings = $this->service->getAll();
        $response->getBody()->write(json_encode($settings, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function post(Request $request, Response $response): Response {
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== Roles::ADMIN) {
            return $response->withStatus(403);
        }
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string)$request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $this->service->save($data);
        return $response->withStatus(204);
    }
}
