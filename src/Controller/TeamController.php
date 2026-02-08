<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TeamService;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\EventService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Manages the list of participating teams.
 */
class TeamController
{
    private TeamService $service;
    private ConfigService $config;
    private ResultService $results;
    private EventService $events;

    /**
     * Inject team service dependency.
     */
    public function __construct(TeamService $service, ConfigService $config, ResultService $results, EventService $events) {
        $this->service = $service;
        $this->config = $config;
        $this->results = $results;
        $this->events = $events;
    }

    /**
     * Set the active event from request, with namespace validation.
     * Returns false if the event_uid is cross-namespace (403 condition).
     */
    private function setEventFromRequest(Request $request): bool {
        $params = $request->getQueryParams();
        $eventUid = (string)($params['event_uid'] ?? '');
        if ($eventUid !== '') {
            $namespace = $request->getAttribute('eventNamespace');
            if (is_string($namespace) && $namespace !== '') {
                if (!$this->events->belongsToNamespace($eventUid, $namespace)) {
                    return false;
                }
            }
            $this->config->setActiveEventUid($eventUid);
        }
        return true;
    }

    /**
     * Return the list of teams as JSON.
     */
    public function get(Request $request, Response $response): Response {
        if (!$this->setEventFromRequest($request)) {
            return $response->withStatus(403);
        }
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
        if (!$this->setEventFromRequest($request)) {
            return $response->withStatus(403);
        }
        $existing = $this->service->getAll();
        $body = (string) $request->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $this->service->saveAll($data);
        if ($existing !== []) {
            $removed = array_values(array_diff($existing, $data));
            if ($removed !== []) {
                $this->results->clearTeams($removed, (string) $this->config->getActiveEventUid());
            }
        }
        return $response->withStatus(204);
    }

    /**
     * Delete all teams and associated results for the active event.
     */
    public function delete(Request $request, Response $response): Response
    {
        if (!$this->setEventFromRequest($request)) {
            return $response->withStatus(403);
        }
        $this->service->deleteAll();
        $uid = (string) $this->config->getActiveEventUid();
        $this->results->clear($uid);

        return $response->withStatus(204);
    }
}
