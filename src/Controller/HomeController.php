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
use PDO;

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
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $cfgSvc = new ConfigService($pdo);
        $eventSvc = new EventService($pdo, $cfgSvc);
        $settingsSvc = new SettingsService($pdo);

        $params = $request->getQueryParams();
        $evParam = (string)($params['event'] ?? '');
        $isUid = preg_match('/^[0-9a-fA-F]{32}$/', $evParam)
            || preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $evParam);
        $uid = $evParam !== '' && !$isUid
            ? $eventSvc->uidBySlug($evParam) ?? ''
            : $evParam;
        if ($uid !== '') {
            $cfgSvc->ensureConfigForEvent($uid);
        }

        $role = $_SESSION['user']['role'] ?? null;
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
                    'role' => $role,
                ]);
            } elseif ($home === 'help') {
                $ctrl = new HelpController();
                return $ctrl($request, $response);
            } elseif ($home === 'landing') {
                $params = $request->getQueryParams();
                if (($params['katalog'] ?? '') === '') {
                    $domainType = $request->getAttribute('domainType');
                    $host = $request->getUri()->getHost();
                    $mainDomain = getenv('MAIN_DOMAIN') ?: '';
                    if ($domainType === null || $domainType === 'main' || $host === $mainDomain) {
                        $ctrl = new \App\Controller\Marketing\LandingController();
                        return $ctrl($request, $response);
                    }
                }
            }
        }
        if ($role !== 'admin') {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }

        $catalogService = new CatalogService($pdo, $cfgSvc, null, '', $uid);

        $catalogsJson = $catalogService->read('catalogs.json');
        $catalogs = [];
        if ($catalogsJson !== null) {
            $catalogs = json_decode($catalogsJson, true) ?? [];
        }

        if (($cfg['competitionMode'] ?? false) === true) {
            $params = $request->getQueryParams();
            $slug = $params['katalog'] ?? '';
            $allowed = array_map(
                static fn($c) => $c['uid'] ?? $c['slug'] ?? $c['sort_order'] ?? '',
                $catalogs
            );
            if ($slug === '' || !in_array($slug, $allowed, true)) {
                return $response
                    ->withHeader('Location', '/help')
                    ->withStatus(302);
            }
        }

        return $view->render($response, 'index.twig', [
            'config' => $cfg,
            'catalogs' => $catalogs,
            'event' => $event,
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'player_name' => $_SESSION['player_name'] ?? '',
        ]);
    }
}
