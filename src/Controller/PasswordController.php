<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PasswordController
{
    private ConfigService $service;

    public function __construct(ConfigService $service)
    {
        $this->service = $service;
    }

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
        $cfg['adminPass'] = $pass;
        $this->service->saveConfig($cfg);

        return $response->withStatus(204);
    }
}
