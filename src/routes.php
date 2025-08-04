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
use App\Controller\CatalogDesignController;
use App\Controller\SummaryController;
use App\Controller\EvidenceController;
use App\Controller\EventController;
use App\Controller\EventListController;
use App\Controller\SettingsController;
use App\Controller\Admin\PageController;
use App\Controller\TenantController;
use App\Controller\Marketing\LandingController;
use App\Controller\RegisterController;
use App\Controller\OnboardingController;
use GuzzleHttp\Client;
use Psr\Log\NullLogger;
use App\Controller\BackupController;
use App\Domain\Roles;

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
require_once __DIR__ . '/Controller/RegisterController.php';
require_once __DIR__ . '/Controller/OnboardingController.php';

use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use Psr\Http\Server\RequestHandlerInterface;

return function (\Slim\App $app, TranslationService $translator) {
    $app->add(function (Request $request, RequestHandlerInterface $handler) use ($translator) {
        $base = Database::connectFromEnv();
        Migrator::migrate($base, __DIR__ . '/../migrations');

        $host = $request->getUri()->getHost();
        $sub = explode('.', $host)[0];
        $stmt = $base->prepare('SELECT subdomain FROM tenants WHERE subdomain = ?');
        $stmt->execute([$sub]);
        $schema = $stmt->fetchColumn();
        $schema = $schema === false ? 'public' : (string) $schema;

        $pdo = Database::connectWithSchema($schema);
        Migrator::migrate($pdo, __DIR__ . '/../migrations');

        $configService = new ConfigService($pdo);
        $catalogService = new CatalogService($pdo, $configService);
        $resultService = new ResultService($pdo, $configService);
        $teamService = new TeamService($pdo, $configService);
        $consentService = new PhotoConsentService($pdo, $configService);
        $summaryService = new SummaryPhotoService($pdo, $configService);
        $eventService = new EventService($pdo);
        $nginxService = new NginxService();
        $tenantService = new TenantService($base, null, $nginxService);
        $userService = new \App\Service\UserService($pdo);
        $settingsService = new \App\Service\SettingsService($pdo);
        $passwordResetService = new PasswordResetService($pdo);

        $request = $request
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
            ->withAttribute('passwordController', new PasswordController($userService))
            ->withAttribute(
                'passwordResetController',
                new PasswordResetController($userService, $passwordResetService)
            )
            ->withAttribute('userController', new UserController($userService))
            ->withAttribute('settingsController', new SettingsController($settingsService))
            ->withAttribute('qrController', new QrController(
                $configService,
                $teamService,
                $eventService,
                $catalogService
            ))
            ->withAttribute('catalogDesignController', new CatalogDesignController($catalogService))
            ->withAttribute('logoController', new LogoController($configService))
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
    $app->get('/onboarding', OnboardingController::class);
    $app->get('/login', [LoginController::class, 'show']);
    $app->post('/login', [LoginController::class, 'login']);
    $app->get('/register', [RegisterController::class, 'show']);
    $app->post('/register', [RegisterController::class, 'register']);
    $app->get('/logout', LogoutController::class);
    $app->get('/admin', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        return $response->withHeader('Location', $base . '/admin/events')->withStatus(302);
    })->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/events', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/event/settings', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/catalogs', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/questions', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/teams', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/summary', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/results', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/statistics', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/pages', AdminController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/management', AdminController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/profile', AdminController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->post('/admin/profile', function (Request $request, Response $response) {
        $controller = new ProfileController();
        return $controller->update($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));
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
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/{path:.*}', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        return $response->withHeader('Location', $base . '/admin/events')->withStatus(302);
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
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));
    $app->post('/events.json', function (Request $request, Response $response) {
        return $request->getAttribute('eventController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));

    $app->post('/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

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

    $app->get('/tenants.json', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->list($request, $response);
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
    $app->post('/import', function (Request $request, Response $response) {
        return $request->getAttribute('importController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/restore-default', function (Request $request, Response $response) {
        return $request->getAttribute('importController')->restoreDefaults($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->post('/tenant-admin', function (Request $request, Response $response) {
        $data = json_decode((string)$request->getBody(), true);
        if (!is_array($data) || !isset($data['schema'], $data['password'])) {
            return $response->withStatus(400);
        }
        $schema = preg_replace('/[^a-z0-9_\-]/i', '', (string)$data['schema']);
        $schema = strtolower($schema);
        if ($schema === '' || $schema === 'public') {
            return $response->withStatus(400);
        }
        $pdo = Database::connectWithSchema($schema);
        Migrator::migrate($pdo, __DIR__ . '/../migrations');
        $userService = new UserService($pdo);
        $existing = $userService->getByUsername('admin');
        if ($existing === null) {
            $userService->create('admin', (string)$data['password'], Roles::ADMIN);
        } else {
            $userService->updatePassword((int)$existing['id'], (string)$data['password']);
        }

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
            return $response->withStatus(403);
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) ($args['slug'] ?? '')));
        $script = realpath(__DIR__ . '/../scripts/onboard_tenant.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Onboard script not found']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $cmd = escapeshellcmd($script . ' ' . $slug) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $msg = implode("\n", $output);
            $first = strtok($msg, "\n");
            $response->getBody()->write(json_encode([
                'error' => $first ?: 'Failed to start tenant',
                'details' => $msg,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'slug' => $slug]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(Roles::SERVICE_ACCOUNT));

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

    $app->get('/database', function (Request $request, Response $response) {
        $uri = $request->getUri();
        $location = 'https://adminer.' . $uri->getHost();
        return $response->withHeader('Location', $location)->withStatus(302);
    })->add(new RoleAuthMiddleware('admin'));
};
