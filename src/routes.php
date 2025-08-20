<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controller\HomeController;
use App\Controller\FaqController;
use App\Controller\HelpController;
use App\Controller\DatenschutzController;
use App\Controller\ImpressumController;
use App\Controller\LizenzController;
use App\Controller\AdminController;
use App\Controller\AdminCatalogController;
use App\Controller\LoginController;
use App\Controller\LogoutController;
use App\Controller\ConfigController;
use App\Controller\CatalogController;
use App\Application\Middleware\RoleAuthMiddleware;
use App\Service\ConfigService;
use App\Service\CatalogService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\PhotoConsentService;
use App\Service\EventService;
use App\Service\SummaryPhotoService;
use App\Service\UserService;
use App\Service\TenantService;
use App\Service\NginxService;
use App\Service\SettingsService;
use App\Service\TranslationService;
use App\Service\PasswordResetService;
use App\Service\PasswordPolicy;
use App\Service\MailService;
use App\Service\EmailConfirmationService;
use App\Service\InvitationService;
use App\Service\AuditLogger;
use App\Service\QrCodeService;
use App\Service\SessionService;
use App\Service\StripeService;
use App\Service\VersionService;
use App\Infrastructure\Database;
use App\Controller\Admin\ProfileController;
use App\Application\Middleware\LanguageMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\RateLimitMiddleware;
use App\Controller\ResultController;
use App\Controller\TeamController;
use App\Controller\PasswordController;
use App\Controller\PasswordResetController;
use App\Controller\UserController;
use App\Controller\ImportController;
use App\Controller\ExportController;
use App\Controller\QrController;
use App\Controller\LogoController;
use App\Controller\QrLogoController;
use App\Controller\CatalogDesignController;
use App\Controller\SummaryController;
use App\Controller\EvidenceController;
use App\Controller\EventController;
use App\Controller\EventListController;
use App\Controller\SettingsController;
use App\Controller\Admin\PageController;
use App\Controller\Admin\LandingpageController;
use App\Controller\TenantController;
use App\Controller\Marketing\LandingController;
use App\Controller\Marketing\ContactController;
use App\Controller\RegisterController;
use App\Controller\OnboardingController;
use App\Controller\OnboardingEmailController;
use App\Controller\StripeCheckoutController;
use App\Controller\StripeSessionController;
use App\Controller\StripeWebhookController;
use App\Controller\SubscriptionController;
use App\Controller\AdminSubscriptionCheckoutController;
use App\Controller\InvitationController;
use Slim\Views\Twig;
use GuzzleHttp\Client;
use Psr\Log\NullLogger;
use App\Controller\BackupController;
use App\Domain\Roles;
use App\Domain\Plan;

require_once __DIR__ . '/Controller/HomeController.php';
require_once __DIR__ . '/Controller/FaqController.php';
require_once __DIR__ . '/Controller/HelpController.php';
require_once __DIR__ . '/Controller/DatenschutzController.php';
require_once __DIR__ . '/Controller/ImpressumController.php';
require_once __DIR__ . '/Controller/LizenzController.php';
require_once __DIR__ . '/Controller/AdminController.php';
require_once __DIR__ . '/Controller/LoginController.php';
require_once __DIR__ . '/Controller/LogoutController.php';
require_once __DIR__ . '/Controller/ConfigController.php';
require_once __DIR__ . '/Controller/CatalogController.php';
require_once __DIR__ . '/Controller/ResultController.php';
require_once __DIR__ . '/Controller/TeamController.php';
require_once __DIR__ . '/Controller/PasswordController.php';
require_once __DIR__ . '/Controller/PasswordResetController.php';
require_once __DIR__ . '/Controller/AdminCatalogController.php';
require_once __DIR__ . '/Controller/Admin/PageController.php';
require_once __DIR__ . '/Controller/Admin/LandingpageController.php';
require_once __DIR__ . '/Controller/QrController.php';
require_once __DIR__ . '/Controller/LogoController.php';
require_once __DIR__ . '/Controller/CatalogDesignController.php';
require_once __DIR__ . '/Controller/SummaryController.php';
require_once __DIR__ . '/Controller/EvidenceController.php';
require_once __DIR__ . '/Controller/ExportController.php';
require_once __DIR__ . '/Controller/EventController.php';
require_once __DIR__ . '/Controller/EventListController.php';
require_once __DIR__ . '/Controller/SettingsController.php';
require_once __DIR__ . '/Controller/BackupController.php';
require_once __DIR__ . '/Controller/UserController.php';
require_once __DIR__ . '/Controller/TenantController.php';
require_once __DIR__ . '/Controller/Marketing/LandingController.php';
require_once __DIR__ . '/Controller/Marketing/ContactController.php';
require_once __DIR__ . '/Controller/RegisterController.php';
require_once __DIR__ . '/Controller/OnboardingController.php';
require_once __DIR__ . '/Controller/OnboardingEmailController.php';
require_once __DIR__ . '/Controller/StripeCheckoutController.php';
require_once __DIR__ . '/Controller/StripeSessionController.php';
require_once __DIR__ . '/Controller/StripeWebhookController.php';
require_once __DIR__ . '/Controller/SubscriptionController.php';
require_once __DIR__ . '/Controller/AdminSubscriptionCheckoutController.php';
require_once __DIR__ . '/Controller/InvitationController.php';

use App\Infrastructure\Migrations\Migrator;
use Psr\Http\Server\RequestHandlerInterface;

return function (\Slim\App $app, TranslationService $translator) {
    $app->add(function (Request $request, RequestHandlerInterface $handler) use ($translator) {
        $base = Database::connectFromEnv();
        Migrator::migrate($base, __DIR__ . '/../migrations');

        $host = $request->getUri()->getHost();
        $domainType = $request->getAttribute('domainType');
        $sub = $domainType === 'main' ? 'main' : explode('.', $host)[0];
        $stmt = $base->prepare('SELECT subdomain FROM tenants WHERE subdomain = ?');
        $stmt->execute([$sub]);
        $schema = $stmt->fetchColumn();
        $schema = $schema === false ? 'public' : (string) $schema;

        $pdo = Database::connectWithSchema($schema);
        Migrator::migrate($pdo, __DIR__ . '/../migrations');

        $nginxService = new NginxService();
        $tenantService = new TenantService($base, null, $nginxService);

        $configService = new ConfigService($pdo);
        $catalogService = new CatalogService($pdo, $configService, $tenantService, $sub);
        $resultService = new ResultService($pdo, $configService);
        $teamService = new TeamService($pdo, $configService, $tenantService, $sub);
        $consentService = new PhotoConsentService($pdo, $configService);
        $summaryService = new SummaryPhotoService($pdo, $configService);
        $eventService = new EventService($pdo, $configService, $tenantService, $sub);
        $plan = $tenantService->getPlanBySubdomain($sub);
        $userService = new \App\Service\UserService($pdo);
        $settingsService = new \App\Service\SettingsService($pdo);
        $passwordResetService = new PasswordResetService(
            $pdo,
            3600,
            getenv('PASSWORD_RESET_SECRET') ?: ''
        );
        $passwordPolicy = new PasswordPolicy();
        $emailConfirmService = new EmailConfirmationService($pdo);
        $auditLogger = new AuditLogger($pdo);
        $sessionService = new SessionService($pdo);

        $request = $request
            ->withAttribute('plan', $plan)
            ->withAttribute('configController', new ConfigController($configService))
            ->withAttribute('catalogController', new CatalogController($catalogService))
            ->withAttribute('resultController', new ResultController(
                $resultService,
                $configService,
                $teamService,
                $catalogService,
                __DIR__ . '/../data/photos',
                $eventService
            ))
            ->withAttribute('teamController', new TeamController($teamService))
            ->withAttribute('eventController', new EventController($eventService))
            ->withAttribute(
                'tenantController',
                new TenantController(
                    $tenantService,
                    filter_var(getenv('DISPLAY_ERROR_DETAILS'), FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->withAttribute('auditLogger', $auditLogger)
            ->withAttribute(
                'passwordController',
                new PasswordController(
                    $userService,
                    $passwordPolicy,
                    $auditLogger,
                    $sessionService
                )
            )
            ->withAttribute(
                'passwordResetController',
                new PasswordResetController($userService, $passwordResetService, $passwordPolicy, $sessionService)
            )
            ->withAttribute('userController', new UserController($userService))
            ->withAttribute('settingsController', new SettingsController($settingsService))
            ->withAttribute('qrController', new QrController(
                $configService,
                $teamService,
                $eventService,
                $catalogService,
                new QrCodeService()
            ))
            ->withAttribute('onboardingEmailController', new OnboardingEmailController($emailConfirmService))
            ->withAttribute('catalogDesignController', new CatalogDesignController($catalogService))
            ->withAttribute('logoController', new LogoController($configService))
            ->withAttribute('qrLogoController', new QrLogoController($configService))
            ->withAttribute('summaryController', new SummaryController($configService, $eventService))
            ->withAttribute('importController', new ImportController(
                $catalogService,
                $configService,
                $resultService,
                $teamService,
                $consentService,
                $summaryService,
                $eventService,
                __DIR__ . '/../data',
                __DIR__ . '/../backup'
            ))
            ->withAttribute('exportController', new ExportController(
                $configService,
                $catalogService,
                $resultService,
                $teamService,
                $consentService,
                $summaryService,
                $eventService,
                __DIR__ . '/../data',
                __DIR__ . '/../backup'
            ))
            ->withAttribute('backupController', new BackupController(__DIR__ . '/../backup'))
            ->withAttribute('evidenceController', new EvidenceController(
                $resultService,
                $consentService,
                $summaryService,
                new NullLogger(),
                __DIR__ . '/../data/photos'
            ))
            ->withAttribute('pdo', $pdo)
            ->withAttribute('translator', $translator)
            ->withAttribute('lang', $translator->getLocale());

        return $handler->handle($request);
    });
    $app->add(new LanguageMiddleware($translator));

    $app->get('/healthz', function (Request $request, Response $response) {
        $version = getenv('APP_VERSION');
        if ($version === false || $version === '') {
            $version = (new VersionService())->getCurrentVersion();
        }
        $payload = [
            'status'  => 'ok',
            'app'     => 'quizrace',
            'version' => $version,
            'time'    => gmdate('c'),
        ];
        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/', HomeController::class);
    $app->get('/favicon.ico', function (Request $request, Response $response) {
        $iconPath = __DIR__ . '/../public/favicon.svg';
        if (file_exists($iconPath)) {
            $response->getBody()->write(file_get_contents($iconPath));
            return $response->withHeader('Content-Type', 'image/svg+xml');
        }
        return $response->withStatus(404);
    });
    $app->get('/faq', FaqController::class);
    $app->get('/help', HelpController::class);
    $app->get('/events', EventListController::class);
    $app->get('/datenschutz', DatenschutzController::class);
    $app->get('/impressum', ImpressumController::class);
    $app->get('/lizenz', LizenzController::class);
    $app->get('/landing', function (Request $request, Response $response) {
        $domainType = $request->getAttribute('domainType');
        if ($domainType === null) {
            $host = $request->getUri()->getHost();
            $mainDomain = getenv('MAIN_DOMAIN') ?: '';
            $domainType = $host === $mainDomain || $mainDomain === '' ? 'main' : 'tenant';
            $request = $request->withAttribute('domainType', $domainType);
        }
        if ($domainType !== 'main') {
            return $response->withStatus(404);
        }
        $controller = new LandingController();
        return $controller($request, $response);
    });
    $app->post('/landing/contact', ContactController::class)
        ->add(new RateLimitMiddleware(3, 3600))
        ->add(new CsrfMiddleware());
    $app->get('/onboarding', OnboardingController::class);
    $app->post('/onboarding/email', function (Request $request, Response $response) {
        return $request->getAttribute('onboardingEmailController')->request($request, $response);
    })->add(new RateLimitMiddleware(3, 3600))->add(new CsrfMiddleware());
    $app->get('/onboarding/email/confirm', function (Request $request, Response $response) {
        return $request->getAttribute('onboardingEmailController')->confirm($request, $response);
    });
    $app->get('/onboarding/email/status', function (Request $request, Response $response) {
        return $request->getAttribute('onboardingEmailController')->status($request, $response);
    });
    $app->post('/onboarding/checkout', StripeCheckoutController::class);
    $app->get('/onboarding/checkout/{id}', StripeSessionController::class);
    $app->post('/stripe/webhook', StripeWebhookController::class);
    $app->get('/login', [LoginController::class, 'show']);
    $app->post('/login', [LoginController::class, 'login']);
    $app->get('/register', [RegisterController::class, 'show']);
    $app->post('/register', [RegisterController::class, 'register']);
    $app->get('/password/reset/request', function (Request $request, Response $response) {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        return $view->render($response, 'password_request.twig', ['csrf_token' => $csrf]);
    });
    $app->get('/password/reset', function (Request $request, Response $response) {
        $view = Twig::fromRequest($request);
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        return $view->render($response, 'password_confirm.twig', [
            'token'      => $token,
            'csrf_token' => $csrf,
            'action'     => '/password/reset/confirm',
        ]);
    });

    $app->get('/password/set', function (Request $request, Response $response) {
        $view = Twig::fromRequest($request);
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        $next = (string) ($request->getQueryParams()['next'] ?? '');
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        return $view->render($response, 'password_confirm.twig', [
            'token'      => $token,
            'csrf_token' => $csrf,
            'action'     => '/password/set',
            'next'       => $next,
        ]);
    });
    $app->get('/logout', LogoutController::class);
    $app->get('/admin', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        return $response->withHeader('Location', $base . '/admin/dashboard')->withStatus(302);
    })->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/dashboard', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/events', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/event/settings', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/catalogs', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/questions', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/teams', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/summary', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/results', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/statistics', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/dashboard.json', function (Request $request, Response $response) {
        $month = (string)($request->getQueryParams()['month'] ?? (new DateTimeImmutable('now'))->format('Y-m'));
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01')
            ?: new DateTimeImmutable('first day of this month');
        $end = $start->modify('first day of next month');
        $stmt = $pdo->prepare(
            'SELECT uid,name,start_date,end_date,published '
            . 'FROM events '
            . 'WHERE start_date >= ? AND start_date < ? '
            . 'ORDER BY start_date'
        );
        $stmt->execute([
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 00:00:00'),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $events = [];
        $now = new DateTimeImmutable('now');
        $teamStmt = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE event_uid = ?');
        foreach ($rows as $row) {
            $startDate = new DateTimeImmutable((string) $row['start_date']);
            $endDate = new DateTimeImmutable((string) $row['end_date']);
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

        $totalCount = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
        $upcomingCount = (int) $pdo->query('SELECT COUNT(*) FROM events WHERE start_date > NOW()')->fetchColumn();
        $pastCount = (int) $pdo->query('SELECT COUNT(*) FROM events WHERE end_date < NOW()')->fetchColumn();

        $stats = [
            'eventCount' => $totalCount,
            'upcomingCount' => $upcomingCount,
            'pastCount' => $pastCount,
            'teamsWithoutQr' => 0,
            'resultsAwaitingReview' => 0,
        ];

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
    })->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/pages', AdminController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/management', AdminController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/profile', AdminController::class)
        ->add(new RoleAuthMiddleware(...Roles::ALL))
        ->add(new CsrfMiddleware());
    $app->get('/admin/subscription', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/subscription/portal', SubscriptionController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
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
    })->add(new RoleAuthMiddleware(...Roles::ALL));
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
    })->add(new RoleAuthMiddleware(...Roles::ALL));
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
        $tenantSvc->updateProfile($target, ['plan' => $plan]);
        $response->getBody()->write((string) json_encode(['plan' => $plan]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->post(
        '/admin/subscription/checkout',
        AdminSubscriptionCheckoutController::class
    )->add(new RoleAuthMiddleware(...Roles::ALL))->add(new CsrfMiddleware());
    $app->get(
        '/admin/subscription/checkout/{id}',
        StripeSessionController::class
    )->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->post('/admin/profile', function (Request $request, Response $response) {
        $controller = new ProfileController();
        return $controller->update($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ALL))->add(new CsrfMiddleware());
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
        Migrator::migrate($pdo, __DIR__ . '/../migrations');
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
        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!MailService::isConfigured()) {
                return $response->withStatus(503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $auditLogger);
        }
        $mainDomain = getenv('MAIN_DOMAIN') ?: getenv('DOMAIN');
        $domain = $mainDomain ? sprintf('%s.%s', $sub, $mainDomain) : $uri->getHost();
        $link = sprintf('https://%s/password/set?token=%s&next=%%2Fadmin', $domain, urlencode($token));
        $html = $mailer->sendWelcome($email, $domain, $link);
        $baseDir = dirname(__DIR__, 1);
        $dir = $baseDir . '/data/' . $sub;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/welcome_email.html', $html);
        return $response->withStatus(204);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->get('/admin/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(404);
        }
        $controller = new AdminController();
        return $controller($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/kataloge', AdminCatalogController::class)
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->get('/admin/pages/{slug}', function (Request $request, Response $response, array $args) {
        $controller = new PageController();
        return $controller->edit($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/pages/{slug}', function (Request $request, Response $response, array $args) {
        $controller = new PageController();
        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/admin/landingpage/seo', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(404);
        }
        $controller = new LandingpageController();
        return $controller->page($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/landingpage/seo', function (Request $request, Response $response) {
        $controller = new LandingpageController();
        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/admin/{path:.*}', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        return $response->withHeader('Location', $base . '/admin/dashboard')->withStatus(302);
    });
    $app->get('/results', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->page($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));

    $app->get('/results.json', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->get($request, $response);
    });

    $app->get('/question-results.json', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->getQuestions($request, $response);
    });

    $app->get('/results/download', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->download($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));

    $app->get('/results.pdf', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->pdf($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));

    $app->post('/results', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->post($request, $response);
    });

    $app->delete('/results', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->delete($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/config.json', function (Request $request, Response $response) {
        return $request->getAttribute('configController')->get($request, $response);
    });

    $app->post('/config.json', function (Request $request, Response $response) {
        return $request->getAttribute('configController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));

    $app->get('/settings.json', function (Request $request, Response $response) {
        return $request->getAttribute('settingsController')->get($request, $response);
    });

    $app->post('/settings.json', function (Request $request, Response $response) {
        return $request->getAttribute('settingsController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->get($req, $response, $args);
    });

    $app->post('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->post($req, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->delete('/kataloge/{file}/{index}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file'])
            ->withAttribute('index', $args['index']);
        return $request->getAttribute('catalogController')->deleteQuestion($req, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->put('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->create($req, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->delete('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->delete($req, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->get('/events.json', function (Request $request, Response $response) {
        return $request->getAttribute('eventController')->get($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->post('/events.json', function (Request $request, Response $response) {
        return $request->getAttribute('eventController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));
    $app->post('/events/{uid}/publish', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('eventController')->publish($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));

    $app->post('/invite', function (Request $request, Response $response) {
        $pdo = $request->getAttribute('pdo');
        $twig = Twig::fromRequest($request)->getEnvironment();
        if (!MailService::isConfigured()) {
            return $response->withStatus(503);
        }
        $mailer = new MailService($twig);
        $service = new InvitationService($pdo);
        $controller = new InvitationController($service, $mailer);
        return $controller->send($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->get('/tenants/export', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->export($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/tenants/report', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->report($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/tenants/{subdomain}', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->exists($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->delete('/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->delete($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->post('/tenants/sync', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->sync($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/tenants.json', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->list($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/tenants/{subdomain}/welcome', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $sub = preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) ($args['subdomain'] ?? '')));
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $tenant = $tenantSvc->getBySubdomain($sub);
        if ($tenant === null || ($tenant['imprint_email'] ?? '') === '') {
            return $response->withStatus(404);
        }
        $email = (string) $tenant['imprint_email'];
        $pdo = Database::connectWithSchema($sub);
        Migrator::migrate($pdo, __DIR__ . '/../migrations');
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
        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!MailService::isConfigured()) {
                return $response->withStatus(503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $auditLogger);
        }
        $mainDomain = getenv('MAIN_DOMAIN') ?: getenv('DOMAIN');
        $domain = $mainDomain ? sprintf('%s.%s', $sub, $mainDomain) : $request->getUri()->getHost();
        $link = sprintf('https://%s/password/set?token=%s&next=%%2Fadmin', $domain, urlencode($token));
        $html = $mailer->sendWelcome($email, $domain, $link);
        $baseDir = dirname(__DIR__, 1);
        $dir = $baseDir . '/data/' . $sub;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/welcome_email.html', $html);
        return $response->withStatus(204);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/tenants/{subdomain}/welcome', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $sub = preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) ($args['subdomain'] ?? '')));
        $base = dirname(__DIR__, 1);
        $file = $base . '/data/' . $sub . '/welcome_email.html';
        if (!is_readable($file)) {
            return $response->withStatus(404);
        }
        $response->getBody()->write((string) file_get_contents($file));
        return $response->withHeader('Content-Type', 'text/html');
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/teams.json', function (Request $request, Response $response) {
        return $request->getAttribute('teamController')->get($request, $response);
    });
    $app->post('/teams.json', function (Request $request, Response $response) {
        return $request->getAttribute('teamController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::TEAM_MANAGER));
    $app->get('/users.json', function (Request $request, Response $response) {
        return $request->getAttribute('userController')->get($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));
    $app->post('/users.json', function (Request $request, Response $response) {
        return $request->getAttribute('userController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));
    $app->post('/password', function (Request $request, Response $response) {
        return $request->getAttribute('passwordController')->post($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->post('/password/reset/request', function (Request $request, Response $response) {
        return $request->getAttribute('passwordResetController')->request($request, $response);
    })->add(new RateLimitMiddleware(3, 3600))->add(new CsrfMiddleware());
    $app->post('/password/reset/confirm', function (Request $request, Response $response) {
        return $request->getAttribute('passwordResetController')->confirm($request, $response);
    })->add(new RateLimitMiddleware(3, 3600))->add(new CsrfMiddleware());
    $app->post('/password/set', function (Request $request, Response $response) {
        return $request->getAttribute('passwordResetController')->confirm($request, $response);
    })->add(new RateLimitMiddleware(3, 3600))->add(new CsrfMiddleware());
    $app->post('/import', function (Request $request, Response $response) {
        return $request->getAttribute('importController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/restore-default', function (Request $request, Response $response) {
        return $request->getAttribute('importController')->restoreDefaults($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->post('/export-default', function (Request $request, Response $response) {
        return $request->getAttribute('exportController')->exportDefaults($request, $response);
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/tenant-welcome', function (Request $request, Response $response) {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data) || !isset($data['schema'], $data['email'])) {
            return $response->withStatus(400);
        }
        $schema = preg_replace('/[^a-z0-9_\-]/i', '', strtolower((string) $data['schema']));
        if ($schema === '' || $schema === 'public') {
            return $response->withStatus(400);
        }
        $email = (string) $data['email'];
        $pdo = Database::connectWithSchema($schema);
        Migrator::migrate($pdo, __DIR__ . '/../migrations');
        $userService = new UserService($pdo);
        $auditLogger = new AuditLogger($pdo);
        $admin = $userService->getByUsername('admin');
        $randomPass = bin2hex(random_bytes(16));
        if ($admin === null) {
            $userService->create('admin', $randomPass, $email, Roles::ADMIN);
            $admin = $userService->getByUsername('admin');
        } else {
            $userService->updatePassword((int)$admin['id'], $randomPass);
            $userService->setEmail((int)$admin['id'], $email);
        }
        if ($admin === null) {
            return $response->withStatus(500);
        }

        $resetService = new PasswordResetService($pdo);
        $token = $resetService->createToken((int)$admin['id']);

        $mainDomain = getenv('MAIN_DOMAIN') ?: getenv('DOMAIN');
        $twig = Twig::fromRequest($request)->getEnvironment();
        if (!MailService::isConfigured()) {
            return $response->withStatus(503);
        }
        $mailer = new MailService($twig, $auditLogger);
        $domain = $mainDomain ? sprintf('%s.%s', $schema, $mainDomain) : $request->getUri()->getHost();
        $link = sprintf('https://%s/password/set?token=%s&next=%%2Fadmin', $domain, urlencode($token));
        $html = $mailer->sendWelcome($email, $domain, $link);
        $base = dirname(__DIR__, 1);
        $dir = $base . '/data/' . $schema;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/welcome_email.html', $html);

        return $response->withStatus(204);
    })->add(new RoleAuthMiddleware(Roles::SERVICE_ACCOUNT));
    $app->post('/import/{name}', function (Request $request, Response $response, array $args) {
        return $request
            ->getAttribute('importController')
            ->import($request->withAttribute('name', $args['name']), $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/export', function (Request $request, Response $response) {
        return $request->getAttribute('exportController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/backups', function (Request $request, Response $response) {
        return $request->getAttribute('backupController')->list($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/backups/{name}/download', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('name', $args['name']);
        return $request->getAttribute('backupController')->download($req, $response, $args);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/backups/{name}/restore', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('name', $args['name']);
        return $request->getAttribute('importController')->import($req, $response, $args);
    })->add(new RoleAuthMiddleware('admin'));
    $app->delete('/backups/{name}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('name', $args['name']);
        return $request->getAttribute('backupController')->delete($req, $response, $args);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/qr.png', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->image($request, $response);
    });
    $app->get('/qr.pdf', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->pdf($request, $response);
    });
    $app->get('/qr/catalog', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->catalog($request, $response);
    });
    // Team QR codes
    $app->get('/qr/team', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->team($request, $response);
    });
    $app->get('/qr/event', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->event($request, $response);
    });
    $app->get('/invites.pdf', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->pdfAll($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/{file:logo(?:-[\w-]+)?\.png}', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->get($request->withAttribute('ext', 'png'), $response);
    });
    $app->post('/logo.png', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/{file:logo(?:-[\w-]+)?\.webp}', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->get($request->withAttribute('ext', 'webp'), $response);
    });
    $app->post('/logo.webp', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));

    $app->get('/{file:qrlogo(?:-[\w-]+)?\.png}', function (Request $request, Response $response) {
        return $request->getAttribute('qrLogoController')->get($request->withAttribute('ext', 'png'), $response);
    });
    $app->post('/qrlogo.png', function (Request $request, Response $response) {
        return $request->getAttribute('qrLogoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/{file:qrlogo(?:-[\w-]+)?\.webp}', function (Request $request, Response $response) {
        return $request->getAttribute('qrLogoController')->get($request->withAttribute('ext', 'webp'), $response);
    });
    $app->post('/qrlogo.webp', function (Request $request, Response $response) {
        return $request->getAttribute('qrLogoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));

    $app->get('/catalog/{slug}/design', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('slug', $args['slug']);
        return $request->getAttribute('catalogDesignController')->get($req, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/catalog/{slug}/design', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('slug', $args['slug']);
        return $request->getAttribute('catalogDesignController')->post($req, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/photos', function (Request $request, Response $response) {
        return $request->getAttribute('evidenceController')->post($request, $response);
    });
    $app->post('/photos/rotate', function (Request $request, Response $response) {
        return $request->getAttribute('evidenceController')->rotate($request, $response);
    });
    $app->get('/photo/{team}/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('team', $args['team'])->withAttribute('file', $args['file']);
        return $request->getAttribute('evidenceController')->get($req, $response);
    });
    $app->get('/summary', function (Request $request, Response $response) {
        return $request->getAttribute('summaryController')($request, $response);
    });

    $app->post('/nginx-reload', function (Request $request, Response $response) {
        $token = $request->getHeaderLine('X-Token');
        $expected = $_ENV['NGINX_RELOAD_TOKEN'] ?? 'changeme';
        $reloaderUrl = $_ENV['NGINX_RELOADER_URL'] ?? 'http://nginx-reloader:8080/reload';

        if ($token !== $expected) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(403);
        }

        $client = $request->getAttribute('httpClient');
        if (!$client instanceof Client) {
            $client = new Client();
        }
        try {
            $client->post($reloaderUrl, [
                'headers' => ['X-Token' => $expected],
            ]);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Reload failed',
                'details' => $e->getMessage(),
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'nginx reloaded']));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/tenants/{slug}/onboard', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            $response->getBody()->write(json_encode(['error' => 'forbidden']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) ($args['slug'] ?? '')));
        $script = realpath(__DIR__ . '/../scripts/onboard_tenant.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Onboard script not found']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $cmd = sprintf('%s %s > /dev/null 2>&1 &', escapeshellcmd($script), escapeshellarg($slug));
        proc_close(proc_open($cmd, [], $pipes));

        $payload = ['status' => 'queued', 'tenant' => $slug];
        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(202);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT))->add(new CsrfMiddleware());

    $app->delete('/api/tenants/{slug}', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) ($args['slug'] ?? '')));
        $script = realpath(__DIR__ . '/../scripts/offboard_tenant.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Offboard script not found']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $cmd = escapeshellcmd($script . ' ' . $slug);
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $response->getBody()->write(json_encode(['error' => 'Failed to remove tenant']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'slug' => $slug]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/api/renew-ssl', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $script = realpath(__DIR__ . '/../scripts/renew_ssl.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Renew script not found']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $cmd = escapeshellcmd($script . ' --main');
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $response->getBody()->write(json_encode(['error' => 'Failed to renew certificate']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'slug' => 'main']));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/api/tenants/{slug}/renew-ssl', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) ($args['slug'] ?? '')));
        $script = realpath(__DIR__ . '/../scripts/renew_ssl.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Renew script not found']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $cmd = escapeshellcmd($script . ' ' . $slug);
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $response->getBody()->write(json_encode(['error' => 'Failed to renew certificate']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'slug' => $slug]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/api/tenants/{slug}/upgrade', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) ($args['slug'] ?? '')));
        $script = realpath(__DIR__ . '/../scripts/upgrade_tenant.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Upgrade script not found']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $cmd = escapeshellcmd($script . ' ' . $slug);
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $response->getBody()->write(json_encode(['error' => 'Failed to upgrade tenant']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'slug' => $slug]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->get('/database', function (Request $request, Response $response) {
        $uri = $request->getUri();
        $location = 'https://adminer.' . $uri->getHost();
        return $response->withHeader('Location', $location)->withStatus(302);
    })->add(new RoleAuthMiddleware('admin'));
};
