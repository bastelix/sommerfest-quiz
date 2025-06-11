<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConfigController
{
    private ConfigService $service;

    public function __construct(ConfigService $service)
    {
        $this->service = $service;
    }

    public function get(Request $request, Response $response): Response
    {
        $content = $this->service->getJs();
        if ($content === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/javascript');
    }

    public function post(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        $this->service->saveConfig($data ?? []);

        return $response->withStatus(204);
    }
}
