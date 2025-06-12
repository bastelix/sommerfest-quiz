<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TeamService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TeamController
{
    private TeamService $service;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }

    public function get(Request $request, Response $response): Response
    {
        $data = $this->service->getAll();
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function post(Request $request, Response $response): Response
    {
        $body = (string) $request->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $this->service->saveAll($data);
        return $response->withStatus(204);
    }
}
