<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TeamService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Manages the list of participating teams.
 */
class TeamController
{
    private TeamService $service;

    /**
     * Inject team service dependency.
     */
    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the list of teams as JSON.
     */
    public function get(Request $request, Response $response): Response
    {
        $data = $this->service->getAll();
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Replace the entire list of teams with the provided data.
     */
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
