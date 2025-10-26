<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ConfigService;
use App\Service\CatalogService;
use App\Service\EventService;
use App\Service\SettingsService;
use App\Service\ResultService;
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
    public function __invoke(Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $cfgSvc = new ConfigService($pdo);
        $eventSvc = new EventService($pdo, $cfgSvc);
        $settingsSvc = new SettingsService($pdo);

        /** @var array<string, string> $params Query string values */
        $params = $request->getQueryParams();

        $catalogParam = (string)($params['katalog'] ?? '');
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
                if ($event !== null) {
                    $cfg = $cfgSvc->getConfigForEvent($event['uid']) ?: [];
                }
            }
            if ($event !== null && (!isset($cfg['event_uid']) || (string) $cfg['event_uid'] === '')) {
                $cfg['event_uid'] = (string) $event['uid'];
            }
            $home = $settingsSvc->get('home_page', 'help');
            $domainStartPage = $request->getAttribute('domainStartPage');
            if (is_string($domainStartPage) && $domainStartPage !== '') {
                $home = $domainStartPage;
            }
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
                if ($catalogParam === '') {
                    $domainType = $request->getAttribute('domainType');
                    $host = $request->getUri()->getHost();
                    $mainDomain = getenv('MAIN_DOMAIN') ?: '';
                    if (
                        $domainType === null
                        || $domainType === 'main'
                        || $host === $mainDomain
                        || $domainType === 'marketing'
                    ) {
                        $ctrl = new \App\Controller\Marketing\LandingController();
                        return $ctrl($request, $response);
                    }
                }
            } elseif ($home === 'calhelp') {
                if ($catalogParam === '') {
                    $ctrl = new \App\Controller\Marketing\MarketingPageController('calhelp');
                    return $ctrl($request, $response);
                }
            } elseif ($home === 'calserver') {
                if ($catalogParam === '') {
                    $ctrl = new \App\Controller\Marketing\CalserverController();
                    return $ctrl($request, $response);
                }
            } elseif ($home === 'calserver-maintenance') {
                if ($catalogParam === '') {
                    $ctrl = new \App\Controller\Marketing\MarketingPageController('calserver-maintenance');
                    return $ctrl($request, $response);
                }
            } elseif ($home === 'future-is-green') {
                if ($catalogParam === '') {
                    $ctrl = new \App\Controller\Marketing\FutureIsGreenController();
                    return $ctrl($request, $response);
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
            $slug = strtolower($catalogParam);
            $allowed = array_map(
                static fn ($v) => strtolower((string) $v),
                array_filter(
                    array_merge(
                        array_column($catalogs, 'slug'),
                        array_column($catalogs, 'uid'),
                        array_column($catalogs, 'sort_order')
                    ),
                    static fn ($v) => $v !== null && $v !== ''
                )
            );
            if ($slug === '' || !in_array($slug, $allowed, true)) {
                return $response
                    ->withHeader('Location', '/help')
                    ->withStatus(302);
            }
            if (($name = (string) ($_SESSION['player_name'] ?? '')) !== '') {
                $resultSvc = new ResultService($pdo);
                if ($resultSvc->exists($name, $slug, $uid)) {
                    return $response
                        ->withHeader('Location', '/help')
                        ->withStatus(302);
                }
            }
        }

        if ($uid !== '' && $catalogParam === '') {
            return $view->render($response, 'event_catalogs.twig', [
                'config' => $cfg,
                'catalogs' => $catalogs,
                'event' => $event,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
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
