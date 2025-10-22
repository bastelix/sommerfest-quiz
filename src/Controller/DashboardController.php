<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\ConfigValidator;
use App\Service\EventService;
use App\Service\ResultService;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Public dashboard for event results secured by a share token.
 */
class DashboardController
{
    private ConfigService $config;
    private EventService $events;
    private ResultService $results;
    private CatalogService $catalogs;

    public function __construct(
        ConfigService $config,
        EventService $events,
        ResultService $results,
        CatalogService $catalogs
    ) {
        $this->config = $config;
        $this->events = $events;
        $this->results = $results;
        $this->catalogs = $catalogs;
    }

    public function view(Request $request, Response $response, array $args): Response
    {
        $context = $this->resolveAccess($request, $args);
        if ($context === null) {
            return $response->withStatus(404);
        }
        [$event, $cfg] = $context;

        $modules = $cfg['dashboardModules'] ?? ConfigValidator::DEFAULT_DASHBOARD_MODULES;
        if (!is_array($modules)) {
            $modules = ConfigValidator::DEFAULT_DASHBOARD_MODULES;
        }

        $refresh = (int) ($cfg['dashboardRefreshInterval'] ?? 15);
        if ($refresh < 5) {
            $refresh = 15;
        }
        $rankingLimit = (int) ($cfg['dashboardRankingLimit'] ?? 5);
        if ($rankingLimit < 1) {
            $rankingLimit = 5;
        }

        $sanitizedConfig = $cfg;
        unset($sanitizedConfig['dashboardShareToken']);
        $sanitizedConfig['dashboardModules'] = $modules;

        return Twig::fromRequest($request)->render($response, 'dashboard.twig', [
            'event' => $event,
            'config' => $sanitizedConfig,
            'modules' => $modules,
            'refreshInterval' => $refresh,
            'rankingLimit' => $rankingLimit,
        ]);
    }

    public function data(Request $request, Response $response, array $args): Response
    {
        $context = $this->resolveAccess($request, $args);
        if ($context === null) {
            return $response->withStatus(404);
        }
        [$event] = $context;

        $results = $this->results->getAll($event['uid']);
        $mappedResults = [];
        $updatedAt = 0;
        foreach ($results as $row) {
            $time = (int) ($row['time'] ?? 0);
            if ($time > $updatedAt) {
                $updatedAt = $time;
            }
            $puzzleTime = isset($row['puzzleTime']) && $row['puzzleTime'] !== null
                ? (int) $row['puzzleTime']
                : null;
            if ($puzzleTime !== null && $puzzleTime > $updatedAt) {
                $updatedAt = $puzzleTime;
            }
            $mappedResults[] = [
                'name' => (string) ($row['name'] ?? ''),
                'catalog' => (string) ($row['catalog'] ?? ''),
                'catalogName' => (string) ($row['catalogName'] ?? ($row['catalog'] ?? '')),
                'attempt' => (int) ($row['attempt'] ?? 0),
                'correct' => (int) ($row['correct'] ?? 0),
                'total' => (int) ($row['total'] ?? 0),
                'time' => $time,
                'puzzleTime' => $puzzleTime,
            ];
        }

        $questionRows = $this->results->getQuestionResults($event['uid']);
        $wrongAnswers = [];
        foreach ($questionRows as $row) {
            $isCorrect = filter_var($row['correct'] ?? false, FILTER_VALIDATE_BOOL);
            if ($isCorrect) {
                continue;
            }
            $wrongAnswers[] = [
                'name' => (string) ($row['name'] ?? ''),
                'catalog' => (string) ($row['catalogName'] ?? ($row['catalog'] ?? '')),
                'prompt' => (string) ($row['prompt'] ?? ''),
            ];
        }

        $payload = [
            'results' => $mappedResults,
            'wrongAnswers' => $wrongAnswers,
            'catalogCount' => $this->catalogs->countCatalogsForEvent($event['uid']),
            'updatedAt' => $updatedAt,
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return $response->withStatus(500);
        }

        $response->getBody()->write($json);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,mixed>}|null
     */
    private function resolveAccess(Request $request, array $args): ?array
    {
        $slug = (string) ($args['slug'] ?? '');
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        if ($slug === '' || $token === '') {
            return null;
        }
        $event = $this->events->getBySlug($slug);
        if ($event === null) {
            return null;
        }
        $cfg = $this->config->getConfigForEvent($event['uid']);
        $enabled = filter_var($cfg['dashboardEnabled'] ?? false, FILTER_VALIDATE_BOOL);
        $storedToken = (string) ($cfg['dashboardShareToken'] ?? '');
        if (!$enabled || $storedToken === '' || !hash_equals($storedToken, $token)) {
            return null;
        }
        return [$event, $cfg];
    }
}
