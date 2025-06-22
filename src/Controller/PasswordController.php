<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Allows changing the administrator password.
 */
class PasswordController
{
    private ConfigService $service;

    /**
     * Inject configuration service.
     */
    public function __construct(ConfigService $service)
    {
        $this->service = $service;
    }

    /**
     * Update the admin password using the provided request body.
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

        $pass = $data['password'] ?? '';
        if (!is_string($pass) || $pass === '') {
            return $response->withStatus(400);
        }

        $cfg = $this->service->getConfig();
        $cfg['adminPass'] = password_hash($pass, PASSWORD_DEFAULT);
        $this->service->saveConfig($cfg);

        return $response->withStatus(204);
    }
}
