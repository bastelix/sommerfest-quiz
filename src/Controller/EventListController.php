<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\EventService;
use App\Service\ConfigService;
use App\Infrastructure\Database;
use PDO;

/**
 * Displays a list of all events for selection.
 */
class EventListController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $eventSvc = new EventService($pdo);
        $cfgSvc = new ConfigService($pdo);
        $events = $eventSvc->getAll();
        $cfg = $cfgSvc->getConfig();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }
        return $view->render($response, 'events_overview.twig', [
            'events' => $events,
            'config' => $cfg,
        ]);
    }
}
