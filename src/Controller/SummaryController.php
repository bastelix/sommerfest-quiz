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
    public function __construct(ConfigService $config)
    {
        $this->config = $config;
        $this->events = new EventService(\App\Infrastructure\Database::connectFromEnv());
    }

    /**
     * Render the summary page.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $cfg = $this->config->getConfig();
        $uid = (string)($cfg['activeEventUid'] ?? '');
        $event = null;
        if ($uid !== '') {
            $event = $this->events->getByUid($uid);
        }
        if ($event === null) {
            $event = $this->events->getFirst();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['admin'])) {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }
        return $view->render($response, 'summary.twig', [
            'config' => $cfg,
            'event' => $event,
        ]);
    }
}
