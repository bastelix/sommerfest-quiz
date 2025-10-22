<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TeamService;
use App\Service\ConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Manages the list of participating teams.
 */
class TeamController
{
    private TeamService $service;
    private ConfigService $config;

    /**
     * Inject team service dependency.
     */
    public function __construct(TeamService $service, ConfigService $config) {
        $this->service = $service;
        $this->config = $config;
    }

    private function setEventFromRequest(Request $request): void {
        $params = $request->getQueryParams();
        if (isset($params['event_uid'])) {
            $this->config->setActiveEventUid((string) $params['event_uid']);
        }
    }

    /**
     * Return the list of teams as JSON.
     */
    public function get(Request $request, Response $response): Response {
        $this->setEventFromRequest($request);
        $data = $this->service->getAll();
        $params = $request->getQueryParams();
        $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 0;
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $page = $page > 0 ? $page : 1;

        if ($perPage > 0) {
            $total = count($data);
            $offset = ($page - 1) * $perPage;
            $slice = array_slice($data, $offset, $perPage);
            $count = count($slice);
            $next = ($offset + $count) < $total ? $page + 1 : null;
            $payload = [
                'items' => array_values($slice),
                'pager' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'count' => $count,
                    'nextPage' => $next,
                ],
            ];
        } else {
            $payload = $data;
        }

        $json = json_encode($payload);
        if ($json === false) {
            return $response->withStatus(500);
        }

        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Replace the entire list of teams with the provided data.
     */
    public function post(Request $request, Response $response): Response {
        $this->setEventFromRequest($request);
        $body = (string) $request->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $this->service->saveAll($data);
        return $response->withStatus(204);
    }
}
