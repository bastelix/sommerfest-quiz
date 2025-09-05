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
        if ($uid !== '') {
            $cfg = $this->config->getConfigForEvent($uid);
            $event = $this->events->getByUid($uid) ?? $this->events->getFirst();
        } else {
            $cfg = $this->config->getConfig();
            $event = null;
            $evUid = (string)($cfg['event_uid'] ?? '');
            if ($evUid !== '') {
                $event = $this->events->getByUid($evUid);
            }
            if ($event === null) {
                $event = $this->events->getFirst();
            }
        }
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
