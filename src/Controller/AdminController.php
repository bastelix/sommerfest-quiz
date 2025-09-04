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
use App\Service\VersionService;
use App\Application\Seo\PageSeoConfigService;
use App\Service\UrlService;
use App\Service\PageService;
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
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $role = $_SESSION['user']['role'] ?? null;
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $cfgSvc = new ConfigService($pdo);
        $eventSvc = new EventService($pdo);
        $settingsSvc = new SettingsService($pdo);
        $settings = $settingsSvc->getAll();
        $versionSvc = new VersionService();
        $version = $versionSvc->getCurrentVersion();

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

        $results   = [];
        $catalogs  = [];
        $teams     = [];
        $users     = [];
        $pages     = [];
        $tenant    = null;
        $tenantSvc = null;
        $sub       = '';

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

        $pageSlugs = ['landing', 'impressum', 'datenschutz', 'faq', 'lizenz'];
        $pageSvc = new PageService();
        foreach ($pageSlugs as $slug) {
            $pages[$slug] = $pageSvc->get($slug) ?? '';
        }

        $domainType = $request->getAttribute('domainType');
        if ($domainType === 'main') {
            $base = Database::connectFromEnv();
            $tenantSvc = new TenantService($base);
            $tenant = $tenantSvc->getMainTenant();
        } else {
            $host = $request->getUri()->getHost();
            $sub  = explode('.', $host)[0];
            $base = Database::connectFromEnv();
            $tenantSvc = new TenantService($base);
            $tenant = $tenantSvc->getBySubdomain($sub);
        }

        if (
            $section === 'subscription'
            && $tenant !== null
            && ($tenant['stripe_customer_id'] ?? '') === ''
            && ($tenant['imprint_email'] ?? '') !== ''
            && StripeService::isConfigured()['ok']
        ) {
            $service = new StripeService();
            try {
                $cid = $service->findCustomerIdByEmail((string) $tenant['imprint_email']);
                if ($cid === null) {
                    $cid = $service->createCustomer(
                        (string) $tenant['imprint_email'],
                        $tenant['imprint_name'] ?? null
                    );
                }
                $tenant['stripe_customer_id'] = $cid;
                if ($sub !== '') {
                    $tenantSvc->updateProfile($sub, ['stripe_customer_id' => $cid]);
                } else {
                    $tenantSvc->updateProfile('main', ['stripe_customer_id' => $cid]);
                }
            } catch (\Throwable $e) {
                // ignore errors; admin page should still render
            }
        }

        $baseUrl = UrlService::determineBaseUrl($request);
        $uri = $request->getUri();

        $mainDomain = getenv('MAIN_DOMAIN')
            ?: getenv('DOMAIN')
            ?: $uri->getHost();

        $seoSvc    = new PageSeoConfigService($pdo);
        $seoConfig = $seoSvc->load(1);

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
              'seo_config' => $seoConfig ? $seoConfig->jsonSerialize() : [],
              'domainType' => $request->getAttribute('domainType'),
              'tenant' => $tenant,
              'stripe_configured' => StripeService::isConfigured()['ok'],
              'stripe_sandbox' => filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN),
              'currentPath' => $request->getUri()->getPath(),
              'username' => $_SESSION['user']['username'] ?? '',
              'csrf_token' => $csrf,
              'version' => $version,
          ]);
    }
}
