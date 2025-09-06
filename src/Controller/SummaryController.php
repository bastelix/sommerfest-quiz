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
    public function __construct(ConfigService $config, EventService $events)
    {
        $this->config = $config;
        $this->events = $events;
    }

    /**
     * Render the summary page.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $params = $request->getQueryParams();
        $uid = (string)($params['event'] ?? '');
        if ($uid === '') {
            $event = $this->events->getFirst();
            if ($event === null) {
                return $response->withHeader('Location', '/events')->withStatus(302);
            }
            $uid = (string)$event['uid'];
        } else {
            $event = $this->events->getByUid($uid);
            if ($event === null) {
                $event = $this->events->getFirst();
                if ($event === null) {
                    return $response->withHeader('Location', '/events')->withStatus(302);
                }
                $uid = (string)$event['uid'];
            }
        }
        $cfg = $this->config->getConfigForEvent($uid);
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }
        return $view->render($response, 'summary.twig', [
            'config' => $cfg,
            'event' => $event,
        ]);
    }
}
