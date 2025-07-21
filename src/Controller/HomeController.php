<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ConfigService;
use App\Service\CatalogService;
use App\Service\EventService;
use App\Service\SettingsService;
use App\Infrastructure\Database;
use Slim\Views\Twig;

/**
 * Entry point for the quiz application home page.
 */
class HomeController
{
    /**
     * Display the start page with catalog selection.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $pdo = Database::connectFromEnv();
        $cfgSvc = new ConfigService($pdo);
        $eventSvc = new EventService($pdo);
        $settingsSvc = new SettingsService($pdo);

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
            $home = $settingsSvc->get('home_page', 'help');
            if ($home === 'events') {
                $events = $eventSvc->getAll();
                return $view->render($response, 'events_overview.twig', [
                    'events' => $events,
                    'config' => $cfg,
                ]);
            } elseif ($home === 'help') {
                $ctrl = new HelpController();
                return $ctrl($request, $response);
            } elseif ($home === 'landing' && $request->getAttribute('domainType') === 'main') {
                $ctrl = new \App\Controller\Marketing\LandingController();
                return $ctrl($request, $response);
            }
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }

        $catalogService = new CatalogService($pdo, new ConfigService($pdo));

        $catalogsJson = $catalogService->read('catalogs.json');
        $catalogs = [];
        if ($catalogsJson !== null) {
            $catalogs = json_decode($catalogsJson, true) ?? [];
        }

        if (($cfg['competitionMode'] ?? false) === true) {
            $params = $request->getQueryParams();
            $slug = $params['katalog'] ?? '';
            $allowedSlugs = array_map(static fn($c) => $c['slug'] ?? '', $catalogs);
            if ($slug === '' || !in_array($slug, $allowedSlugs, true)) {
                return $response
                    ->withHeader('Location', '/help')
                    ->withStatus(302);
            }
        }

        return $view->render($response, 'index.twig', [
            'config' => $cfg,
            'catalogs' => $catalogs,
            'event' => $event,
        ]);
    }
}
