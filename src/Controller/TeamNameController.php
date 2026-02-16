<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\ConfigValidator;
use App\Service\EventService;
use App\Service\TeamNameService;
use InvalidArgumentException;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function max;
use function min;
use function strtolower;
use function trim;

/**
 * HTTP endpoints for reserving and confirming curated team names.
 */
class TeamNameController
{
    private const MAX_BATCH_SIZE = 10;
    private const DEFAULT_HISTORY_LIMIT = 100;
    private const MAX_HISTORY_LIMIT = 500;

    private TeamNameService $service;
    private ConfigService $config;
    private ?EventService $events;

    public function __construct(TeamNameService $service, ConfigService $config, ?EventService $events = null)
    {
        $this->service = $service;
        $this->config = $config;
        $this->events = $events;
    }

    public function reserve(Request $request, Response $response): Response
    {
        $data = $this->parseBody($request);
        $eventId = $this->resolveEventId($data);
        if ($eventId === '' || !$this->validateEventNamespace($eventId, $request)) {
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
        if ($eventId === '' || !$this->validateEventNamespace($eventId, $request)) {
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

    public function status(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $eventId = $this->resolveEventId($query);

        if ($eventId !== '' && !$this->validateEventNamespace($eventId, $request)) {
            return $response->withStatus(400);
        }

        $config = $eventId !== ''
            ? $this->config->getConfigForEvent($eventId)
            : [];

        if ($config === []) {
            $config = $this->config->getConfig();
        }

        $domainsRaw = is_array($config['randomNameDomains'] ?? null) ? $config['randomNameDomains'] : [];
        $tonesRaw = is_array($config['randomNameTones'] ?? null) ? $config['randomNameTones'] : [];
        $domains = $this->normalizeStringList($domainsRaw);
        $tones = $this->normalizeStringList($tonesRaw);
        $buffer = $this->resolveRandomNameBuffer($config);
        $locale = $this->resolveRandomNameLocale($config);
        $strategy = $this->resolveRandomNameStrategy($config);

        $diagnostics = $this->service->getAiDiagnostics();
        $cache = $this->service->getAiCacheState($eventId);
        $required = $strategy === 'ai';
        $diagnostics['required_for_event'] = $required;
        $diagnostics['active_for_event'] = $required && !empty($diagnostics['available']);
        $diagnostics['cache'] = $cache;

        $lexicon = $this->service->getLexiconInventory($eventId, $domains, $tones);

        $payload = [
            'event_id' => $eventId,
            'strategy' => $strategy,
            'buffer' => $buffer,
            'locale' => $locale,
            'domains' => $domains,
            'tones' => $tones,
            'ai' => $diagnostics,
            'lexicon' => $lexicon,
        ];

        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function preview(Request $request, Response $response): Response
    {
        $data = $this->parseBody($request);
        $eventId = $this->resolveEventId($data);
        if ($eventId === '' || !$this->validateEventNamespace($eventId, $request)) {
            return $response->withStatus(400);
        }

        $domains = is_array($data['domains'] ?? null)
            ? $this->normalizeStringList($data['domains'])
            : [];
        $tones = is_array($data['tones'] ?? null)
            ? $this->normalizeStringList($data['tones'])
            : [];

        $localeValue = $data['locale'] ?? null;
        $locale = null;
        if (is_string($localeValue) || is_numeric($localeValue)) {
            $candidate = trim((string) $localeValue);
            if ($candidate !== '') {
                $locale = $candidate;
            }
        }

        $count = $data['count'] ?? null;
        if (!is_numeric($count)) {
            $count = 5;
        }
        $count = max(1, min((int) $count, 20));

        $report = $this->service->warmUpAiSuggestionsWithLog($eventId, $domains, $tones, $locale, $count);
        $cache = is_array($report['cache'] ?? null) ? $report['cache'] : $this->service->getAiCacheState($eventId);
        $log = is_array($report['log'] ?? null) ? $report['log'] : $this->service->getAiLastLog();

        $payload = [
            'event_id' => $eventId,
            'filters' => [
                'domains' => $domains,
                'tones' => $tones,
                'locale' => $locale,
                'count' => $count,
            ],
            'cache' => $cache,
            'log' => $log,
        ];

        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function history(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $eventId = $this->resolveEventId($query);
        if ($eventId === '' || !$this->validateEventNamespace($eventId, $request)) {
            return $response->withStatus(400);
        }

        $limit = $this->resolveHistoryLimit($query['limit'] ?? null);

        try {
            $entries = $this->service->listNamesForEvent($eventId, $limit);
        } catch (InvalidArgumentException $exception) {
            return $response->withStatus(400);
        } catch (PDOException $exception) {
            return $response->withStatus(500);
        }

        $payload = [
            'event_id' => $eventId,
            'entries' => $entries,
            'limit' => $limit,
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
        if ($eventId === '' || !$this->validateEventNamespace($eventId, $request)) {
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
        if ($eventId === '' || !$this->validateEventNamespace($eventId, $request)) {
            return $response->withStatus(400);
        }

        $released = $this->service->release($eventId, $token);
        if (!$released) {
            return $response->withStatus(404);
        }

        return $response->withStatus(204);
    }

    public function releaseByName(Request $request, Response $response): Response
    {
        $data = $this->parseBody($request);
        $eventId = $this->resolveEventId($data);
        $name = trim((string) ($data['name'] ?? ''));
        if ($eventId === '' || $name === '' || !$this->validateEventNamespace($eventId, $request)) {
            return $response->withStatus(400);
        }

        $this->service->releaseByName($eventId, $name);

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

    private function validateEventNamespace(string $eventId, Request $request): bool
    {
        if ($eventId === '' || $this->events === null) {
            return true;
        }

        $namespace = $request->getAttribute('eventNamespace');
        if (!is_string($namespace) || $namespace === '') {
            return true;
        }

        return $this->events->belongsToNamespace($eventId, $namespace);
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

    /**
     * @param array<int|string, mixed> $values
     * @return array<int, string>
     */
    private function normalizeStringList(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $values = array_map(static function ($value): string {
            if (is_string($value) || is_numeric($value)) {
                return trim((string) $value);
            }

            return '';
        }, $values);

        $values = array_filter($values, static fn (string $value): bool => $value !== '');

        /** @var array<int, string> $unique */
        $unique = array_values(array_unique($values));

        return $unique;
    }

    /**
     * @param mixed $value
     */
    private function resolveHistoryLimit($value): int
    {
        if ($value === null) {
            return self::DEFAULT_HISTORY_LIMIT;
        }

        if (is_numeric($value)) {
            $limit = (int) $value;
            if ($limit <= 0) {
                return self::DEFAULT_HISTORY_LIMIT;
            }

            if ($limit > self::MAX_HISTORY_LIMIT) {
                return self::MAX_HISTORY_LIMIT;
            }

            return $limit;
        }

        return self::DEFAULT_HISTORY_LIMIT;
    }
}
