<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\NamespaceResolver;
use App\Service\NamespaceAppearanceService;
use App\Infrastructure\Database;
use PDO;

/**
 * Presents the help page with configuration settings.
 */
class HelpController
{
    /**
     * Render the help view.
     */
    public function __invoke(Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $pdo = \App\Support\RequestDatabase::resolve($request);
        $cfgSvc = new ConfigService($pdo);
        $eventSvc = new EventService($pdo);
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $appearance = (new NamespaceAppearanceService())->load($namespace);

        $params = $request->getQueryParams();
        $uid = (string)($params['event'] ?? '');
        if ($uid !== '') {
            $cfg = $cfgSvc->getConfigForEvent($uid);
            $event = $eventSvc->getByUid($uid) ?? $eventSvc->getFirst();
        } else {
            $cfg = $cfgSvc->getConfig();
            $event = null;
            $evUid = (string)($cfg['event_uid'] ?? '');
            if ($evUid !== '') {
                $event = $eventSvc->getByUid($evUid);
            }
            if ($event === null) {
                $event = $eventSvc->getFirst();
            }
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }

        if (!empty($cfg['inviteText'])) {
            $invite = ConfigService::sanitizeHtml((string)$cfg['inviteText']);
            $invite = str_ireplace('[team]', 'Team´s', $invite);
            if ($event !== null) {
                $invite = str_ireplace('[event_name]', (string)$event['name'], $invite);
                $invite = str_ireplace('[event_start]', (string)($event['start_date'] ?? ''), $invite);
                $invite = str_ireplace('[event_end]', (string)($event['end_date'] ?? ''), $invite);
                $invite = str_ireplace('[event_description]', (string)($event['description'] ?? ''), $invite);
            }
            $cfg['inviteText'] = $invite;
        }

        return $view->render($response, 'help.twig', [
            'appearance' => $appearance,
            'config' => $cfg,
            'event' => $event,
            'pageNamespace' => $namespace,
        ]);
    }
}
