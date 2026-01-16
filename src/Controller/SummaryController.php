<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ConfigService;
use App\Service\EventService;
use Slim\Views\Twig;

/**
 * Shows the overall quiz summary page.
 */
class SummaryController
{
    private ConfigService $config;
    private EventService $events;

    /**
     * Inject configuration service dependency.
     */
    public function __construct(ConfigService $config, EventService $events) {
        $this->config = $config;
        $this->events = $events;
    }

    /**
     * Render the summary page.
     */
    public function __invoke(Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $params = $request->getQueryParams();
        $eventUidParam = (string)($params['event_uid'] ?? '');
        $eventParam = (string)($params['event'] ?? '');
        $uid = '';
        $event = null;

        if ($eventUidParam !== '') {
            $uid = $eventUidParam;
            $event = $this->events->getByUid($uid);
        }

        if ($event === null && $eventParam !== '') {
            $event = $this->events->getByUid($eventParam) ?? $this->events->getBySlug($eventParam);
            if ($event !== null) {
                $uid = (string) $event['uid'];
            }
        }

        if ($eventUidParam === '' && $eventParam !== '' && $uid !== '') {
            $redirectParams = $params;
            unset($redirectParams['event']);
            $redirectParams['event_uid'] = $uid;
            $uri = $request->getUri()->withQuery(http_build_query($redirectParams, '', '&', PHP_QUERY_RFC3986));
            return $response->withHeader('Location', (string) $uri)->withStatus(302);
        }
        $forceResults = $this->shouldForceResults($params);
        if ($uid !== '') {
            $cfg = $this->config->getConfigForEvent($uid);
            $event = $event ?? $this->events->getFirst();
            if ($event === null) {
                return $response->withHeader('Location', '/events')->withStatus(302);
            }
        } else {
            $event = $this->events->getFirst();
            if ($event === null) {
                return $response->withHeader('Location', '/events')->withStatus(302);
            }
            $uid = (string)$event['uid'];
            $cfg = $this->config->getConfigForEvent($uid);
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }
        return $view->render($response, 'summary.twig', [
            'config' => $cfg,
            'event' => $event,
            'forceResults' => $forceResults,
        ]);
    }

    /**
     * Determine if the request explicitly forces the results view.
     *
     * @param array<string, mixed> $params
     */
    private function shouldForceResults(array $params): bool
    {
        $keys = ['results', 'showResults', 'forceResults'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $params)) {
                continue;
            }

            $value = $params[$key];
            if (is_bool($value)) {
                if ($value) {
                    return true;
                }
                continue;
            }

            if ($value === null) {
                return true;
            }

            $normalized = strtolower(trim((string) $value));
            if ($normalized === '') {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
