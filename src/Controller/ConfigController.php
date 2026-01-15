<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\ConfigValidator;
use App\Service\EventService;
use App\Domain\Roles;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API endpoints for reading and updating application configuration.
 */
class ConfigController
{
    private ConfigService $service;
    private ConfigValidator $validator;
    private EventService $events;

    /**
     * Inject configuration service dependency.
     */
    public function __construct(ConfigService $service, ConfigValidator $validator, EventService $events) {
        $this->service   = $service;
        $this->validator = $validator;
        $this->events    = $events;
    }

    /**
     * Return the current configuration as JSON.
     */
    public function get(Request $request, Response $response): Response {
        $cfg = $this->service->getConfig();
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
     * Return configuration for the specified event UID.
     */
    public function getByEvent(Request $request, Response $response, array $args): Response {
        $uid = (string) ($args['uid'] ?? '');
        $cfg = $this->service->getConfigForEvent($uid);
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
    public function post(Request $request, Response $response): Response {
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

        if (isset($data['event_uid'])) {
            $uid = (string) $data['event_uid'];
            if ($this->events->getByUid($uid) === null) {
                return $response->withStatus(404);
            }
            $this->service->setActiveEventUid($uid);
            unset($data['event_uid']);
            if ($data === []) {
                return $response->withStatus(204);
            }
        }

        $validation = $this->validator->validate($data);
        if ($validation['errors'] !== []) {
            $response->getBody()->write(json_encode(['errors' => $validation['errors']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $this->service->saveConfig($validation['config']);

        return $response->withStatus(204);
    }
}
