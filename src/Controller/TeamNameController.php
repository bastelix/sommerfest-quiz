<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\ConfigValidator;
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
    private const MAX_BATCH_SIZE = 10;

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

        $config = $this->config->getConfigForEvent($eventId);
        if ($config === []) {
            $config = $this->config->getConfig();
        }

        $domains = is_array($config['randomNameDomains'] ?? null) ? $config['randomNameDomains'] : [];
        $tones = is_array($config['randomNameTones'] ?? null) ? $config['randomNameTones'] : [];
        $buffer = $this->resolveRandomNameBuffer($config);
        $locale = $this->resolveRandomNameLocale($config);
        $strategy = $this->resolveRandomNameStrategy($config);

        try {
            $reservation = $this->service->reserveWithBuffer(
                $eventId,
                $domains,
                $tones,
                $buffer,
                $locale,
                $strategy
            );
        } catch (InvalidArgumentException | PDOException $exception) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $payload = $reservation;
        $payload['event_id'] = $eventId;
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function reserveBatch(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $eventId = $this->resolveEventId($query);
        if ($eventId === '') {
            return $response->withStatus(400);
        }

        $config = $this->config->getConfigForEvent($eventId);
        if ($config === []) {
            $config = $this->config->getConfig();
        }

        $domains = is_array($config['randomNameDomains'] ?? null) ? $config['randomNameDomains'] : [];
        $tones = is_array($config['randomNameTones'] ?? null) ? $config['randomNameTones'] : [];
        $buffer = $this->resolveRandomNameBuffer($config);
        $locale = $this->resolveRandomNameLocale($config);
        $strategy = $this->resolveRandomNameStrategy($config);

        $countParam = $query['count'] ?? null;
        $count = is_numeric($countParam) ? (int) $countParam : 0;
        if ($count <= 0) {
            $count = 1;
        }
        $count = min($count, self::MAX_BATCH_SIZE);

        try {
            $reservations = $this->service->reserveBatchWithBuffer(
                $eventId,
                $count,
                $domains,
                $tones,
                $buffer,
                $locale,
                $strategy
            );
        } catch (InvalidArgumentException | PDOException $exception) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $payload = [
            'event_id' => $eventId,
            'reservations' => $reservations,
        ];

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
     * @param array<mixed> $config
     */
    private function resolveRandomNameBuffer(array $config): int
    {
        $value = $config['randomNameBuffer'] ?? null;
        if (is_numeric($value)) {
            $buffer = (int) $value;
            if ($buffer < ConfigValidator::RANDOM_NAME_BUFFER_MIN) {
                return ConfigValidator::RANDOM_NAME_BUFFER_MIN;
            }
            if ($buffer > ConfigValidator::RANDOM_NAME_BUFFER_MAX) {
                return ConfigValidator::RANDOM_NAME_BUFFER_MAX;
            }

            return $buffer;
        }

        return ConfigValidator::RANDOM_NAME_BUFFER_MIN;
    }

    /**
     * @param array<mixed> $config
     */
    private function resolveRandomNameLocale(array $config): ?string
    {
        $candidate = $config['randomNameLocale']
            ?? $config['locale']
            ?? $config['language']
            ?? null;

        if ($candidate === null) {
            return null;
        }

        $locale = trim((string) $candidate);
        return $locale === '' ? null : $locale;
    }

    /**
     * @param array<mixed> $config
     */
    private function resolveRandomNameStrategy(array $config): string
    {
        $value = $config['randomNameStrategy'] ?? null;
        if (is_string($value) || is_numeric($value)) {
            $candidate = strtolower(trim((string) $value));
            if (in_array($candidate, ConfigValidator::RANDOM_NAME_STRATEGIES, true)) {
                return $candidate;
            }
        }

        return ConfigValidator::RANDOM_NAME_STRATEGY_DEFAULT;
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
