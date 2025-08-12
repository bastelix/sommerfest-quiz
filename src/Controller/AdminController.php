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
use App\Service\SettingsService;
use App\Service\UserService;
use App\Service\TenantService;
use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Service\StripeService;
use PDO;

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
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $cfgSvc = new ConfigService($pdo);
        $eventSvc = new EventService($pdo);
        $settingsSvc = new SettingsService($pdo);
        $settings = $settingsSvc->getAll();

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
        $context = \Slim\Routing\RouteContext::fromRequest($request);
        $route   = $context->getRoute();
        $section = 'dashboard';
        if ($route !== null) {
            $pattern = $route->getPattern();
            $section = ltrim(substr($pattern, strlen('/admin')), '/');
            if ($section === '') {
                $section = 'dashboard';
            }
        }

        $results  = [];
        $catalogs = [];
        $teams    = [];
        $users    = [];
        $pages    = [];
        $tenant   = null;

        $configSvc = new ConfigService($pdo);
        if (in_array($section, ['results', 'catalogs', 'questions', 'summary'], true)) {
            $catalogSvc   = new CatalogService($pdo, $configSvc);
            $catalogsJson = $catalogSvc->read('catalogs.json');
            if ($catalogsJson !== null) {
                $catalogs = json_decode($catalogsJson, true) ?? [];
            }
        }

        if ($section === 'results') {
            $results  = (new ResultService($pdo, $configSvc))->getAll();
            $catMap   = [];
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
            foreach ($results as &$row) {
                $cat = $row['catalog'] ?? '';
                if (isset($catMap[$cat])) {
                    $row['catalogName'] = $catMap[$cat];
                }
            }
            unset($row);
        }

        if (in_array($section, ['teams', 'summary'], true)) {
            $teams = (new TeamService($pdo, $configSvc))->getAll();
        }

        if ($section === 'management') {
            $users = (new UserService($pdo))->getAll();
        }

        $pageSlugs = ['landing', 'impressum', 'datenschutz', 'faq'];
        foreach ($pageSlugs as $slug) {
            $path       = dirname(__DIR__, 2) . '/content/' . $slug . '.html';
            $pages[$slug] = is_file($path) ? file_get_contents($path) : '';
        }

        $domainType = $request->getAttribute('domainType');
        if ($domainType === 'main') {
            $path = dirname(__DIR__, 2) . '/data/profile.json';
            if (is_file($path)) {
                $data = json_decode((string) file_get_contents($path), true);
                if (is_array($data)) {
                    $tenant = $data;
                }
            }
        } else {
            $host = $request->getUri()->getHost();
            $sub  = explode('.', $host)[0];
            $base = Database::connectFromEnv();
            $tenantSvc = new TenantService($base);
            $tenant = $tenantSvc->getBySubdomain($sub);
        }

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

        $mainDomain = getenv('MAIN_DOMAIN')
            ?: getenv('DOMAIN')
            ?: $uri->getHost();

          return $view->render($response, 'admin.twig', [
              'config' => $cfg,
              'settings' => $settings,
              'results' => $results,
              'catalogs' => $catalogs,
              'teams' => $teams,
              'users' => $users,
              'roles' => Roles::ALL,
              'baseUrl' => $baseUrl,
              'main_domain' => $mainDomain,
              'event' => $event,
              'role' => $role,
              'pages' => $pages,
              'domainType' => $request->getAttribute('domainType'),
              'tenant' => $tenant,
              'stripe_configured' => StripeService::isConfigured(),
              'currentPath' => $request->getUri()->getPath(),
          ]);
    }
}
