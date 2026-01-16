<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ConfigService;
use App\Service\EventService;
use Slim\Views\Twig;

/**
 * Renders a lightweight player ranking page for quiz participants.
 */
class RankingController
{
    private ConfigService $config;
    private EventService $events;

    public function __construct(ConfigService $config, EventService $events)
    {
        $this->config = $config;
        $this->events = $events;
    }

    public function __invoke(Request $request, Response $response): Response
    {
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

        if ($uid !== '') {
            $event = $event ?? $this->events->getFirst();
            if ($event === null) {
                return $response->withHeader('Location', '/events')->withStatus(302);
            }
            $uid = (string) $event['uid'];
        } else {
            $event = $this->events->getFirst();
            if ($event === null) {
                return $response->withHeader('Location', '/events')->withStatus(302);
            }
            $uid = (string)$event['uid'];
        }

        $cfg = $this->config->getConfigForEvent($uid);
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }

        return $view->render($response, 'ranking.twig', [
            'config' => $cfg,
            'event' => $event,
        ]);
    }
}
