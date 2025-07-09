<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\CatalogService;
use App\Service\TeamService;
use App\Service\EventService;
use App\Service\UserService;
use App\Domain\Roles;
use App\Infrastructure\Database;

/**
 * Shows the main administration dashboard.
 */
class AdminController
{
    /**
     * Render the admin dashboard page.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $role = $_SESSION['user']['role'] ?? null;
        $pdo = Database::connectFromEnv();
        $cfg = (new ConfigService($pdo))->getConfig();
        $eventSvc = new EventService($pdo);
        $event = null;
        $uid = (string)($cfg['event_uid'] ?? '');
        if ($uid !== '') {
            $event = $eventSvc->getByUid($uid);
        }
        if ($event === null) {
            $event = $eventSvc->getFirst();
        }
        $configSvc = new ConfigService($pdo);
        $results = (new ResultService($pdo, $configSvc))->getAll();
        $catalogSvc = new CatalogService($pdo, $configSvc);
        $catalogsJson = $catalogSvc->read('catalogs.json');
        $catalogs = [];
        $catMap = [];
        if ($catalogsJson !== null) {
            $catalogs = json_decode($catalogsJson, true) ?? [];
            foreach ($catalogs as $c) {
                $name = $c['name'] ?? '';
                if (isset($c['uid'])) {
                    $catMap[$c['uid']] = $name;
                }
                if (isset($c['sort_order'])) {
                    $catMap[$c['sort_order']] = $name;
                }
                if (isset($c['slug'])) {
                    $catMap[$c['slug']] = $name;
                }
            }
        }
        foreach ($results as &$row) {
            $cat = $row['catalog'] ?? '';
            if (isset($catMap[$cat])) {
                $row['catalogName'] = $catMap[$cat];
            }
        }
        unset($row);

        $uri    = $request->getUri();
        $domain = getenv('DOMAIN');
        if ($domain !== false && $domain !== '') {
            if (preg_match('#^https?://#', $domain) === 1) {
                $baseUrl = rtrim($domain, '/');
            } else {
                $baseUrl = 'https://' . $domain;
            }
        } else {
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
            $port    = $uri->getPort();
            if ($port !== null && !in_array($port, [80, 443], true)) {
                $baseUrl .= ':' . $port;
            }
        }

        $teams  = (new TeamService($pdo, $configSvc))->getAll();
        $users  = (new UserService($pdo))->getAll();
        return $view->render($response, 'admin.twig', [
            'config' => $cfg,
            'results' => $results,
            'catalogs' => $catalogs,
            'teams' => $teams,
            'users' => $users,
            'roles' => Roles::ALL,
            'baseUrl' => $baseUrl,
            'event' => $event,
            'role' => $role,
        ]);
    }
}
