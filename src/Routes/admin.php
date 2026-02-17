<?php

// Admin UI and dashboard routes.
// Extracted from routes.php for maintainability.

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Application\Middleware\RoleAuthMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\NamespaceQueryMiddleware;
use App\Application\Seo\PageSeoConfigService;
use App\Controller\AdminController;
use App\Controller\AdminMediaController;
use App\Controller\AdminLogsController;
use App\Controller\Admin\SystemMetricsController;
use App\Controller\Admin\ProjectController;
use App\Controller\Admin\ProjectPagesController;
use App\Controller\Admin\NavigationController;
use App\Controller\Admin\PagesDesignController;
use App\Controller\Admin\MarketingNewsletterController;
use App\Controller\Admin\NewsletterCampaignController;
use App\Controller\Admin\DomainPageController;
use App\Controller\Admin\BackupController as AdminBackupController;
use App\Controller\SubscriptionController;
use App\Controller\AdminSubscriptionCheckoutController;
use App\Controller\StripeSessionController;
use App\Controller\Admin\ProfileController;
use App\Controller\Admin\PageController as AdminPageController;
use App\Controller\Admin\PageAiController;
use App\Controller\Admin\CmsPageWikiController;
use App\Controller\Admin\MarketingMenuDefinitionController;
use App\Controller\Admin\MarketingMenuItemController;
use App\Controller\Admin\MarketingMenuAssignmentController;
use App\Controller\Admin\MarketingMenuController;
use App\Controller\Admin\MarketingFooterBlockController;
use App\Controller\Admin\PageModuleController as AdminPageModuleController;
use App\Controller\Admin\LandingNewsController as AdminLandingNewsController;
use App\Controller\Admin\LandingpageController;
use App\Domain\Roles;
use App\Domain\Plan;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\MigrationRuntime;
use App\Service\ConfigService;
use App\Service\MediaLibraryService;
use App\Service\LandingMediaReferenceService;
use App\Service\LandingNewsService;
use App\Service\PageService;
use App\Service\EventService;
use App\Service\MarketingNewsletterConfigService;
use App\Service\CmsPageWikiArticleService;
use App\Service\TenantService;
use App\Service\StripeService;
use App\Service\UserService;
use App\Service\AuditLogger;
use App\Service\PasswordResetService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\SettingsService;
use App\Service\NamespaceValidator;
use App\Service\NamespaceResolver;
use App\Repository\NamespaceRepository;
use Slim\Views\Twig;

return function (\Slim\App $app, NamespaceQueryMiddleware $namespaceQueryMiddleware): void {
    $resolveMediaController = function (Request $request): ?AdminMediaController {
        $controller = $request->getAttribute('adminMediaController');
        if ($controller instanceof AdminMediaController) {
            return $controller;
        }
        $service = $request->getAttribute('mediaLibraryService');
        $config = $request->getAttribute('configService');
        if (!$service instanceof MediaLibraryService || !$config instanceof ConfigService) {
            return null;
        }
        $landing = $request->getAttribute('landingMediaReferenceService');
        if (!$landing instanceof LandingMediaReferenceService) {
            $pdo = Database::connectFromEnv();
            $landing = new LandingMediaReferenceService(
                new PageService($pdo),
                new PageSeoConfigService($pdo),
                $config,
                new LandingNewsService($pdo)
            );
        }
        return new AdminMediaController($service, $landing);
    };

    $app->get('/admin', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        return $response->withHeader('Location', $base . '/admin/dashboard')->withStatus(302);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/dashboard', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/events', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/event/settings', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/event/dashboard', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/konfig', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        return $response->withHeader('Location', $base . '/admin/event/settings')->withStatus(302);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/questions', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/teams', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/summary', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/results', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/statistics', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/logs', AdminLogsController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/system/metrics', SystemMetricsController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/media', AdminController::class)
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR, Roles::CUSTOMER));
    $mediaAuth = new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR, Roles::CUSTOMER);
    $mc = $resolveMediaController;
    $app->get('/admin/media/files', function (Request $req, Response $res) use ($mc): Response {
        $c = $mc($req);
        return $c !== null ? $c->list($req, $res) : $res->withStatus(500);
    })->add($mediaAuth)->add(new CsrfMiddleware());
    $app->post('/admin/media/upload', function (Request $req, Response $res) use ($mc): Response {
        $c = $mc($req);
        return $c !== null ? $c->upload($req, $res) : $res->withStatus(500);
    })->add($mediaAuth)->add(new CsrfMiddleware());
    $app->post('/admin/media/replace', function (Request $req, Response $res) use ($mc): Response {
        $c = $mc($req);
        return $c !== null ? $c->replace($req, $res) : $res->withStatus(500);
    })->add($mediaAuth)->add(new CsrfMiddleware());
    $app->post('/admin/media/convert', function (Request $req, Response $res) use ($mc): Response {
        $c = $mc($req);
        return $c !== null ? $c->convert($req, $res) : $res->withStatus(500);
    })->add($mediaAuth)->add(new CsrfMiddleware());
    $app->post('/admin/media/rename', function (Request $req, Response $res) use ($mc): Response {
        $c = $mc($req);
        return $c !== null ? $c->rename($req, $res) : $res->withStatus(500);
    })->add($mediaAuth)->add(new CsrfMiddleware());
    $app->post('/admin/media/delete', function (Request $req, Response $res) use ($mc): Response {
        $c = $mc($req);
        return $c !== null ? $c->delete($req, $res) : $res->withStatus(500);
    })->add($mediaAuth)->add(new CsrfMiddleware());
    $app->get('/admin/dashboard.json', function (Request $request, Response $response) {
        $params = $request->getQueryParams();
        $month = (string)($params['month'] ?? (new DateTimeImmutable('now'))->format('Y-m'));
        $namespaceValidator = new NamespaceValidator();
        $namespaceQuery = $params['namespace'] ?? '';
        $namespace = '';
        if (is_string($namespaceQuery) && trim($namespaceQuery) !== '') {
            $namespace = $namespaceValidator->normalizeCandidate($namespaceQuery) ?? PageService::DEFAULT_NAMESPACE;
        }
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $configService = $request->getAttribute('configService');
        if (!$configService instanceof ConfigService) {
            $configService = new ConfigService($pdo);
        }
        $pageService = new PageService($pdo);
        $newsletterService = new MarketingNewsletterConfigService($pdo);
        $wikiService = new CmsPageWikiArticleService($pdo);
        $landingNewsService = new LandingNewsService($pdo);
        $mediaReferenceService = new LandingMediaReferenceService(
            $pageService,
            new PageSeoConfigService($pdo),
            $configService,
            $landingNewsService
        );
        $eventService = new EventService($pdo, $configService);
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01')
            ?: new DateTimeImmutable('first day of this month');
        $end = $start->modify('first day of next month');
        $rows = $eventService->getAll();
        $rows = array_filter($rows, static function (array $row) use ($start, $end): bool {
            $startValue = $row['start_date'] ?? null;
            if ($startValue === null || $startValue === '') {
                return false;
            }
            $eventStart = new DateTimeImmutable((string) $startValue);
            $endValue = $row['end_date'] ?? null;
            $eventEnd = $endValue ? new DateTimeImmutable((string) $endValue) : $eventStart;
            return $eventStart < $end && $eventEnd >= $start;
        });
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) ($left['start_date'] ?? ''), (string) ($right['start_date'] ?? ''));
        });
        $events = [];
        $now = new DateTimeImmutable('now');
        $teamStmt = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE event_uid = ?');
        foreach ($rows as $row) {
            $startDate = new DateTimeImmutable((string) $row['start_date']);
            $endDate = $row['end_date'] !== null && $row['end_date'] !== ''
                ? new DateTimeImmutable((string) $row['end_date'])
                : $startDate;
            $teamStmt->execute([$row['uid']]);
            $teams = (int) $teamStmt->fetchColumn();
            if (!(bool) $row['published']) {
                $status = 'draft';
            } elseif ($startDate > $now) {
                $status = 'scheduled';
            } elseif ($endDate < $now) {
                $status = 'finished';
            } else {
                $status = 'running';
            }
            $events[] = [
                'id' => $row['uid'],
                'title' => $row['name'],
                'start' => $startDate->format(DateTimeInterface::ATOM),
                'end' => $endDate->format(DateTimeInterface::ATOM),
                'status' => $status,
                'teams' => $teams,
                'stations' => 0,
            ];
        }

        $upcoming = [];
        foreach ($events as $e) {
            $startDate = new DateTimeImmutable($e['start']);
            $diff = $now->diff($startDate)->days;
            if ($startDate >= $now && $diff <= 7) {
                $upcoming[] = [
                    'id' => $e['id'],
                    'title' => $e['title'],
                    'start' => $e['start'],
                    'days' => $diff,
                ];
            }
        }

        $pageTree = $pageService->getTree();
        $pagesByNamespace = [];
        $treeByNamespace = [];
        $knownNamespaces = [];
        $countPages = static function (array $nodes) use (&$countPages): int {
            $total = 0;
            foreach ($nodes as $node) {
                $children = $node['children'] ?? [];
                $total += 1;
                if (is_array($children) && $children !== []) {
                    $total += $countPages($children);
                }
            }

            return $total;
        };

        foreach ($pageTree as $section) {
            $normalized = $namespaceValidator->normalizeCandidate($section['namespace'] ?? null)
                ?? PageService::DEFAULT_NAMESPACE;
            if ($namespace !== '' && $normalized !== $namespace) {
                continue;
            }
            $treeByNamespace[$normalized] = $section['pages'] ?? [];
            $knownNamespaces[$normalized] = true;
        }

        $pages = $pageService->getAll();
        foreach ($pages as $page) {
            $normalized = $namespaceValidator->normalizeCandidate($page->getNamespace())
                ?? PageService::DEFAULT_NAMESPACE;
            if ($namespace !== '' && $normalized !== $namespace) {
                continue;
            }
            $pagesByNamespace[$normalized][] = $page;
            $knownNamespaces[$normalized] = true;
        }

        foreach ($newsletterService->getNamespaces() as $newsletterNamespace) {
            $normalized = $namespaceValidator->normalizeCandidate($newsletterNamespace)
                ?? PageService::DEFAULT_NAMESPACE;
            if ($namespace !== '' && $normalized !== $namespace) {
                continue;
            }
            $knownNamespaces[$normalized] = true;
        }

        $namespaceRepository = new NamespaceRepository($pdo);
        foreach ($namespaceRepository->list() as $namespaceEntry) {
            $normalized = $namespaceValidator->normalizeCandidate($namespaceEntry['namespace'])
                ?? PageService::DEFAULT_NAMESPACE;
            if ($namespace !== '' && $normalized !== $namespace) {
                continue;
            }
            $knownNamespaces[$normalized] = true;
        }

        $namespaces = $namespace !== '' ? [$namespace] : array_keys($knownNamespaces);
        sort($namespaces);

        $stats = [
            'pages' => 0,
            'wiki' => 0,
            'news' => 0,
            'newsletter' => 0,
            'media' => 0,
        ];

        foreach ($namespaces as $ns) {
            $stats['pages'] += $countPages($treeByNamespace[$ns] ?? []);
            $namespacePages = $pagesByNamespace[$ns] ?? [];
            foreach ($namespacePages as $page) {
                $stats['wiki'] += count($wikiService->getArticlesForPage($page->getId()));
                $stats['news'] += count($landingNewsService->getAll($page->getId()));
            }

            $newsletterConfigs = $newsletterService->getAllGrouped($ns);
            $stats['newsletter'] += count(array_keys($newsletterConfigs));

            $mediaReferences = $mediaReferenceService->collect($ns);
            $stats['media'] += count($mediaReferences['files']) + count($mediaReferences['missing']);
        }

        $usageStmt = $pdo->query('SELECT COUNT(*) FROM events');
        $eventCount = (int) $usageStmt->fetchColumn();
        $catCount = (int) $pdo->query('SELECT COUNT(*) FROM catalogs')->fetchColumn();
        $qCount = (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
        $domainType = (string) $request->getAttribute('domainType');
        $host = $request->getUri()->getHost();
        $sub = $domainType === 'main' ? 'main' : explode('.', $host)[0];
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $plan = $tenantSvc->getPlanBySubdomain($sub);
        $limits = $tenantSvc->getLimitsBySubdomain($sub);
        $payload = [
            'period' => $start->format('Y-m'),
            'today' => $now->format('Y-m-d'),
            'events' => $events,
            'upcoming' => $upcoming,
            'stats' => $stats,
            'subscription' => [
                'plan' => $plan,
                'limits' => $limits,
                'usage' => [
                    'events' => $eventCount,
                    'catalogs' => $catCount,
                    'questions' => $qCount,
                ],
            ],
        ];
        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/projects', function (Request $request, Response $response) {
        $controller = new ProjectController();
        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);
    $app->post('/admin/projects/settings', function (Request $request, Response $response) {
        $controller = new ProjectController();
        return $controller->updateSettings($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware());
    $app->get('/admin/pages', function (Request $request, Response $response) {
        $controller = new ProjectPagesController();
        return $controller->content($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);
    $app->get('/admin/pages/content', function (Request $request, Response $response) {
        $controller = new ProjectPagesController();
        return $controller->content($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);
    $app->post('/admin/pages/page-types', function (Request $request, Response $response) {
        $controller = new ProjectPagesController();
        return $controller->savePageTypes($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);
    $app->get('/admin/pages/cookies', function (Request $request, Response $response) {
        $controller = new ProjectPagesController();
        return $controller->cookies($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/navigation', function (Request $request, Response $response) {
        return $response
            ->withHeader('Location', $request->getAttribute('basePath') . '/admin/navigation/menus')
            ->withStatus(302);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/pages/navigation', function (Request $request, Response $response) {
        return $response
            ->withHeader('Location', $request->getAttribute('basePath') . '/admin/navigation/menus')
            ->withStatus(302);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/navigation/menus', function (Request $request, Response $response) {
        $controller = new NavigationController();
        return $controller->menusIndex($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/navigation/footer', function (Request $request, Response $response) {
        $controller = new NavigationController();
        return $controller->footerIndex($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/navigation/footer-blocks', function (Request $request, Response $response) {
        return $response
            ->withHeader('Location', $request->getAttribute('basePath') . '/admin/navigation/footer')
            ->withStatus(302);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/settings/header', function (Request $request, Response $response) {
        $controller = new NavigationController();
        return $controller->headerSettings($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/pages/seo', function (Request $request, Response $response) {
        $controller = new ProjectPagesController();
        return $controller->seo($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/pages/wiki', function (Request $request, Response $response) {
        $controller = new ProjectPagesController();
        return $controller->wiki($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);
    $app->get('/admin/pages/design', function (Request $request, Response $response) {
        $controller = new PagesDesignController();
        return $controller->show($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::DESIGNER, Roles::REDAKTEUR))->add($namespaceQueryMiddleware);
    $app->post('/admin/pages/design', function (Request $request, Response $response) {
        $controller = new PagesDesignController();
        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::DESIGNER))->add(new CsrfMiddleware());
    $app->get('/admin/newsletter', function (Request $request, Response $response) {
        $controller = new MarketingNewsletterController();
        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->get('/admin/newsletter-campaigns', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        $query = $request->getUri()->getQuery();
        $separator = $query !== '' ? '&' : '?';
        $target = $base . '/admin/newsletter' . ($query !== '' ? '?' . $query : '') . $separator . 'tab=campaigns';
        return $response->withHeader('Location', $target)->withStatus(302);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);
    $app->post('/admin/newsletter-campaigns', function (Request $request, Response $response) {
        /** @var NewsletterCampaignController $controller */
        $controller = $request->getAttribute('newsletterCampaignController');
        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->post('/admin/newsletter-campaigns/{id}/send', function (Request $request, Response $response, array $args) {
        /** @var NewsletterCampaignController $controller */
        $controller = $request->getAttribute('newsletterCampaignController');
        return $controller->send($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->get('/admin/logins', AdminController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/management', function (Request $request, Response $response) {
        return $response
            ->withHeader('Location', $request->getAttribute('basePath') . '/admin/domains')
            ->withStatus(302);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/domains', DomainPageController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/backups', AdminBackupController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/rag-chat', AdminController::class)
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR, Roles::CUSTOMER));
    $app->get('/admin/profile', AdminController::class)
        ->add(new RoleAuthMiddleware(...Roles::ADMIN_UI))
        ->add(new CsrfMiddleware());
    $app->get('/admin/subscription', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/subscription/portal', SubscriptionController::class)
        ->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/subscription/status', function (Request $request, Response $response) {
        $domainType = (string) $request->getAttribute('domainType');
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $tenant = $domainType === 'main'
            ? $tenantSvc->getMainTenant()
            : $tenantSvc->getBySubdomain(explode('.', $request->getUri()->getHost())[0]);
        $customerId = (string) ($tenant['stripe_customer_id'] ?? '');
        $payload = [];
        if ($customerId !== '' && StripeService::isConfigured()['ok']) {
            $service = new StripeService();
            try {
                $info = $service->getActiveSubscription($customerId);
                if ($info !== null) {
                    $payload = $info;
                }
            } catch (\Throwable $e) {
                // ignore errors
            }
        }
        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/subscription/invoices', function (Request $request, Response $response) {
        $domainType = (string) $request->getAttribute('domainType');
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $tenant = $domainType === 'main'
            ? $tenantSvc->getMainTenant()
            : $tenantSvc->getBySubdomain(explode('.', $request->getUri()->getHost())[0]);
        $customerId = (string) ($tenant['stripe_customer_id'] ?? '');
        $payload = [];
        if ($customerId !== '' && StripeService::isConfigured()['ok']) {
            $service = new StripeService();
            try {
                $payload = $service->listInvoices($customerId);
            } catch (\Throwable $e) {
                // ignore errors
            }
        }
        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->post('/admin/subscription/toggle', function (Request $request, Response $response) {
        $domainType = $request->getAttribute('domainType');
        $target = 'main';
        if ($domainType !== 'main') {
            $sub = explode('.', $request->getUri()->getHost())[0];
            if ($sub !== 'demo') {
                return $response->withStatus(403);
            }
            $target = 'demo';
        }
        $data = json_decode((string) $request->getBody(), true);
        $plan = $data['plan'] ?? null;
        if ($plan === '') {
            $plan = null;
        }
        $allowed = [null, Plan::STARTER->value, Plan::STANDARD->value, Plan::PROFESSIONAL->value];
        if (!in_array($plan, $allowed, true)) {
            return $response->withStatus(400);
        }
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        try {
            $tenant = $tenantSvc->getBySubdomain($target) ?? [];
            $customerId = $tenant['stripe_customer_id'] ?? null;
            if ($customerId !== null && $customerId !== '') {
                $stripeSvc = new StripeService();
                if ($plan !== null) {
                    $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
                    $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
                    $map = [
                        'starter' => getenv($prefix . 'PRICE_STARTER') ?: '',
                        'standard' => getenv($prefix . 'PRICE_STANDARD') ?: '',
                        'professional' => getenv($prefix . 'PRICE_PROFESSIONAL') ?: '',
                    ];
                    $priceId = $map[$plan];
                    if ($priceId === '') {
                        throw new \RuntimeException('price-id-missing');
                    }
                    $stripeSvc->updateSubscriptionForCustomer($customerId, $priceId);
                } else {
                    $stripeSvc->cancelSubscriptionForCustomer($customerId);
                }
            }
            $tenantSvc->updateProfile($target, ['plan' => $plan]);
        } catch (\Throwable $e) {
            error_log('Stripe subscription update failed: ' . $e->getMessage());
            return $response->withStatus(500);
        }
        $response->getBody()->write((string) json_encode(['plan' => $plan]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->post(
        '/admin/subscription/checkout',
        AdminSubscriptionCheckoutController::class
    )->add(new RoleAuthMiddleware(...Roles::ADMIN_UI))->add(new CsrfMiddleware());
    $app->get(
        '/admin/subscription/checkout/{id}',
        StripeSessionController::class
    )->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->post('/admin/profile', function (Request $request, Response $response) {
        $controller = new ProfileController();
        return $controller->update($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI))->add(new CsrfMiddleware());
    $app->post('/admin/profile/welcome', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'tenant') {
            return $response->withStatus(403);
        }
        $uri = $request->getUri();
        $sub = explode('.', $uri->getHost())[0];
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $tenant = $tenantSvc->getBySubdomain($sub);
        if ($tenant === null || ($tenant['imprint_email'] ?? '') === '') {
            return $response->withStatus(404);
        }
        $email = (string) $tenant['imprint_email'];
        $pdo = Database::connectWithSchema($sub);
        MigrationRuntime::ensureUpToDate($pdo, __DIR__ . '/../../migrations', 'schema:' . $sub);
        $userService = new UserService($pdo);
        $auditLogger = new AuditLogger($pdo);
        $admin = $userService->getByUsername('admin');
        $randomPass = bin2hex(random_bytes(16));
        if ($admin === null) {
            $userService->create('admin', $randomPass, $email, Roles::ADMIN);
            $admin = $userService->getByUsername('admin');
        } else {
            $userService->updatePassword((int) $admin['id'], $randomPass);
            $userService->setEmail((int) $admin['id'], $email);
        }
        if ($admin === null) {
            return $response->withStatus(500);
        }
        $resetService = new PasswordResetService($pdo);
        $token = $resetService->createToken((int) $admin['id']);

        $providerManager = $request->getAttribute('mailProviderManager');
        if (!$providerManager instanceof MailProviderManager) {
            $providerNamespace = (new NamespaceResolver())->resolve($request)->getNamespace();
            $providerManager = new MailProviderManager(
                new SettingsService(Database::connectFromEnv()),
                [],
                null,
                $providerNamespace
            );
        }

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!$providerManager->isConfigured()) {
                return $response->withStatus(503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $providerManager, $auditLogger);
        }
        $mainDomain = getenv('MAIN_DOMAIN') ?: getenv('DOMAIN');
        $domain = $mainDomain ? sprintf('%s.%s', $sub, $mainDomain) : $uri->getHost();
        $link = sprintf('https://%s/password/set?token=%s&next=%%2Fadmin', $domain, urlencode($token));
        $mailer->sendWelcome($email, $domain, $link);

        return $response->withStatus(204);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->get('/admin/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(404);
        }
        $controller = new AdminController();
        return $controller($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/catalogs', AdminController::class)
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR, Roles::CUSTOMER));
    $app->get('/admin/catalogs/data', function (Request $request, Response $response) {
        $controller = $request->getAttribute('adminCatalogController');
        return $controller->catalogs($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR, Roles::CUSTOMER))->add($namespaceQueryMiddleware);
    $app->get('/admin/catalogs/sample', function (Request $request, Response $response) {
        $controller = $request->getAttribute('adminCatalogController');
        return $controller->sample($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->get('/admin/pages/tree', function (Request $request, Response $response) {
        $controller = new AdminPageController();
        return $controller->tree($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->get('/admin/projects/tree', function (Request $request, Response $response) {
        $controller = new ProjectController();
        return $controller->tree($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER));

    $app->get('/admin/pages/{slug}', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->edit($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->get('/admin/pages/{pageId:[0-9]+}/wiki', function (Request $request, Response $response, array $args) {
        $controller = new CmsPageWikiController();

        return $controller->index($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->get('/admin/menus', function (Request $request, Response $response) {
        $controller = new MarketingMenuDefinitionController();

        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/menus/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuDefinitionController();

        return $controller->show($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/menus', function (Request $request, Response $response) {
        $controller = new MarketingMenuDefinitionController();

        return $controller->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->patch('/admin/menus/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuDefinitionController();

        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->delete('/admin/menus/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuDefinitionController();

        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/menus/{menuId:[0-9]+}/items', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuItemController();

        return $controller->index($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/menus/{menuId:[0-9]+}/items', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuItemController();

        return $controller->create($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->patch('/admin/menus/{menuId:[0-9]+}/items/{id:[0-9]+}', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new MarketingMenuItemController();

        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->delete('/admin/menus/{menuId:[0-9]+}/items/{id:[0-9]+}', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new MarketingMenuItemController();

        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/menu-assignments', function (Request $request, Response $response) {
        $controller = new MarketingMenuAssignmentController();

        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/menu-assignments/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuAssignmentController();

        return $controller->show($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/menu-assignments', function (Request $request, Response $response) {
        $controller = new MarketingMenuAssignmentController();

        return $controller->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->patch('/admin/menu-assignments/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuAssignmentController();

        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->delete('/admin/menu-assignments/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuAssignmentController();

        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/pages/{pageId:[0-9]+}/menu', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuController();

        return $controller->index($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/menu', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuController();

        return $controller->save($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->delete('/admin/pages/{pageId:[0-9]+}/menu', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuController();

        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/menu/sort', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuController();

        return $controller->sort($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/pages/{pageId:[0-9]+}/menu/export', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuController();

        return $controller->export($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/menu/import', function (Request $request, Response $response, array $args) {
        $controller = new MarketingMenuController();

        return $controller->import($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/menu/ai', function (Request $request, Response $response, array $args) {
        $controller = new ProjectPagesController();

        return $controller->generateMenu($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/menu/translate', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new ProjectPagesController();

        return $controller->translateMenu($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    // Footer Block routes
    $app->get('/admin/footer-blocks', function (Request $request, Response $response) {
        $controller = new MarketingFooterBlockController();

        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/footer-blocks', function (Request $request, Response $response) {
        $controller = new MarketingFooterBlockController();

        return $controller->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->put('/admin/footer-blocks/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingFooterBlockController();

        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->delete('/admin/footer-blocks/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingFooterBlockController();

        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/footer-blocks/reorder', function (Request $request, Response $response) {
        $controller = new MarketingFooterBlockController();

        return $controller->reorder($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->put('/admin/footer-blocks/layout', function (Request $request, Response $response) {
        $controller = new MarketingFooterBlockController();

        return $controller->saveLayout($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/startpage', function (Request $request, Response $response, array $args) {
        $controller = new ProjectPagesController();

        return $controller->updateStartpage($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/theme', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->updateTheme($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/settings', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->updateSettings($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->saveArticle($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}/status', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->updateStatus($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}/start', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->updateStartDocument($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}/duplicate', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->duplicate($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->showArticle($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->get('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}/download', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->download($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->delete('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles/sort', function (
        Request $request,
        Response $response,
        array $args
    ) {
        $controller = new CmsPageWikiController();

        return $controller->sort($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/import-create', function (Request $request, Response $response) {
        $controller = new AdminPageController();
        return $controller->createFromImport($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/ai-generate', function (Request $request, Response $response) {
        $controller = new PageAiController();

        return $controller->generate($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/pages/ai-generate/status', function (Request $request, Response $response) {
        $controller = new PageAiController();

        return $controller->status($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{slug}', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{slug}/copy', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->copy($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{slug}/move', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->move($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/pages/{slug}/export', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->export($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{slug}/import', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->import($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{slug}/namespace', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->updateNamespace($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages/{slug}/rename', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->rename($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->delete('/admin/pages/{slug}', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageController();
        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/pages', function (Request $request, Response $response) {
        $controller = new AdminPageController();
        return $controller->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/landing-news', function (Request $request, Response $response) {
        $controller = new AdminLandingNewsController();
        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);
    $app->get('/admin/landing-news/create', function (Request $request, Response $response) {
        $controller = new AdminLandingNewsController();
        return $controller->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);
    $app->post('/admin/landing-news', function (Request $request, Response $response) {
        $controller = new AdminLandingNewsController();
        return $controller->store($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);
    $app->get('/admin/landing-news/{id:\d+}', function (Request $request, Response $response, array $args) {
        $controller = new AdminLandingNewsController();
        return $controller->edit($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);
    $app->post('/admin/landing-news/{id:\d+}', function (Request $request, Response $response, array $args) {
        $controller = new AdminLandingNewsController();
        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);
    $app->post('/admin/landing-news/{id:\d+}/delete', function (Request $request, Response $response, array $args) {
        $controller = new AdminLandingNewsController();
        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/page-modules', function (Request $request, Response $response) {
        $controller = new AdminPageModuleController();
        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add($namespaceQueryMiddleware);

    $app->post('/admin/page-modules', function (Request $request, Response $response) {
        $controller = new AdminPageModuleController();
        return $controller->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->put('/admin/page-modules/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageModuleController();
        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->delete('/admin/page-modules/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new AdminPageModuleController();
        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CUSTOMER))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/admin/landingpage/seo', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(404);
        }
        $controller = new LandingpageController();
        return $controller->page($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add($namespaceQueryMiddleware);

    $app->post('/admin/landingpage/seo', function (Request $request, Response $response) {
        $controller = new LandingpageController();
        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->post('/admin/landingpage/seo/ai-import', function (Request $request, Response $response) {
        $controller = new LandingpageController();

        return $controller->importFromAi($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware())->add($namespaceQueryMiddleware);

    $app->get('/results', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->page($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST, Roles::CUSTOMER));

    $app->get('/results.json', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->get($request, $response);
    });

    $app->get('/question-results.json', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->getQuestions($request, $response);
    });

    $app->get('/event/{slug}/dashboard/{token}', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('dashboardController')->view($request, $response, $args);
    });

    $app->get('/results/download', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->download($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST, Roles::CUSTOMER));

    $app->get('/results.pdf', function (Request $request, Response $response) {
        $team = $request->getQueryParams()['team'] ?? null;
        if ($team !== null) {
            $request = $request->withAttribute('team', (string) $team);
        }

        return $request->getAttribute('resultController')->pdf($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST, Roles::CUSTOMER));

    $app->post('/results', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->post($request, $response);
    });
};
