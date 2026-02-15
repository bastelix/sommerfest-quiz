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
    public function __invoke(Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $pdo = \App\Support\RequestDatabase::resolve($request);
        $eventSvc = new EventService($pdo);
        $cfgSvc = new ConfigService($pdo);
        $role = $_SESSION['user']['role'] ?? null;
        $namespace = $request->getAttribute('eventNamespace')
            ?? $request->getAttribute('pageNamespace');
        $events = $eventSvc->getAll(is_string($namespace) && $namespace !== '' ? $namespace : null);
        $cfg = $cfgSvc->getConfig();
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }
        return $view->render($response, 'events_overview.twig', [
            'events' => $events,
            'config' => $cfg,
            'role' => $role,
            'eventNamespace' => is_string($namespace) && $namespace !== '' ? $namespace : '',
        ]);
    }
}
