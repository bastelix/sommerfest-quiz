<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\NamespaceResolver;
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
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $params = $request->getQueryParams();
        $uid = (string)($params['event'] ?? $params['event_uid'] ?? '');

        if ($uid !== '') {
            $event = $this->events->getByUid($uid, $namespace);
            if ($event === null) {
                $event = $this->events->getFirst($namespace);
                if ($event === null) {
                    return $response->withHeader('Location', '/events')->withStatus(302);
                }
                $uid = (string)$event['uid'];
            }
        } else {
            $event = $this->events->getFirst($namespace);
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
