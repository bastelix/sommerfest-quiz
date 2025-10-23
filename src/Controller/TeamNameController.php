<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\TeamNameService;
use InvalidArgumentException;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * HTTP endpoints for reserving and confirming curated team names.
 */
class TeamNameController
{
    private TeamNameService $service;
    private ConfigService $config;

    public function __construct(TeamNameService $service, ConfigService $config)
    {
        $this->service = $service;
        $this->config = $config;
    }

    public function reserve(Request $request, Response $response): Response
    {
        $data = $this->parseBody($request);
        $eventId = $this->resolveEventId($data);
        if ($eventId === '') {
            return $response->withStatus(400);
        }

        try {
            $reservation = $this->service->reserve($eventId);
        } catch (InvalidArgumentException | PDOException $exception) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $payload = $reservation;
        $payload['event_id'] = $eventId;
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function confirm(Request $request, Response $response, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        if ($token === '') {
            return $response->withStatus(400);
        }
        $data = $this->parseBody($request);
        $eventId = $this->resolveEventId($data);
        if ($eventId === '') {
            return $response->withStatus(400);
        }

        $expectedName = isset($data['name']) ? (string) $data['name'] : null;
        $result = $this->service->confirm($eventId, $token, $expectedName);
        if ($result === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'event_id' => $eventId,
            'name' => $result['name'],
            'fallback' => $result['fallback'],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function release(Request $request, Response $response, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        if ($token === '') {
            return $response->withStatus(400);
        }
        $data = $this->parseBody($request);
        $eventId = $this->resolveEventId($data);
        if ($eventId === '') {
            return $response->withStatus(400);
        }

        $released = $this->service->release($eventId, $token);
        if (!$released) {
            return $response->withStatus(404);
        }

        return $response->withStatus(204);
    }

    /**
     * @param array<mixed> $data
     */
    private function resolveEventId(array $data): string
    {
        $event = (string) ($data['event_uid'] ?? $data['event_id'] ?? '');
        if ($event !== '') {
            return $event;
        }
        $config = $this->config->getConfig();
        return (string) ($config['event_uid'] ?? '');
    }

    /**
     * @return array<mixed>
     */
    private function parseBody(Request $request): array
    {
        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
