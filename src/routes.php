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
use App\Service\UserService;
use App\Service\TenantService;
use App\Controller\ResultController;
use App\Controller\TeamController;
use App\Controller\PasswordController;
use App\Controller\UserController;
use App\Controller\ImportController;
use App\Controller\ExportController;
use App\Controller\QrController;
use App\Controller\LogoController;
use App\Controller\CatalogDesignController;
use App\Controller\SummaryController;
use App\Controller\EvidenceController;
use App\Controller\EventController;
use App\Controller\TenantController;
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
require_once __DIR__ . '/Controller/AdminCatalogController.php';
require_once __DIR__ . '/Controller/QrController.php';
require_once __DIR__ . '/Controller/LogoController.php';
require_once __DIR__ . '/Controller/CatalogDesignController.php';
require_once __DIR__ . '/Controller/SummaryController.php';
require_once __DIR__ . '/Controller/EvidenceController.php';
require_once __DIR__ . '/Controller/ExportController.php';
require_once __DIR__ . '/Controller/EventController.php';
require_once __DIR__ . '/Controller/BackupController.php';
require_once __DIR__ . '/Controller/UserController.php';
require_once __DIR__ . '/Controller/TenantController.php';

use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use Psr\Http\Server\RequestHandlerInterface;

return function (\Slim\App $app) {
    $app->add(function (Request $request, RequestHandlerInterface $handler) {
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
        $eventService = new EventService($pdo);
        $tenantService = new TenantService($pdo);
        $userService = new \App\Service\UserService($pdo);

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
            ->withAttribute('tenantController', new TenantController($tenantService))
            ->withAttribute('passwordController', new PasswordController($userService))
            ->withAttribute('userController', new UserController($userService))
            ->withAttribute('qrController', new QrController($configService, $teamService, $eventService, $catalogService))
            ->withAttribute('catalogDesignController', new CatalogDesignController($catalogService))
            ->withAttribute('logoController', new LogoController($configService))
            ->withAttribute('summaryController', new SummaryController($configService))
            ->withAttribute('importController', new ImportController(
                $catalogService,
                $configService,
                $resultService,
                $teamService,
                $consentService,
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
                $eventService,
                __DIR__ . '/../data',
                __DIR__ . '/../backup'
            ))
            ->withAttribute('backupController', new BackupController(__DIR__ . '/../backup'))
            ->withAttribute('evidenceController', new EvidenceController(
                $resultService,
                $consentService,
                new NullLogger(),
                __DIR__ . '/../data/photos'
            ))
            ->withAttribute('pdo', $pdo);

        return $handler->handle($request);
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
    $app->get('/datenschutz', DatenschutzController::class);
    $app->get('/impressum', ImpressumController::class);
    $app->get('/lizenz', LizenzController::class);
    $app->get('/login', [LoginController::class, 'show']);
    $app->post('/login', [LoginController::class, 'login']);
    $app->get('/logout', LogoutController::class);
    $app->get('/admin', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->get('/admin/kataloge', AdminCatalogController::class)
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
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
        return $request->getAttribute('tenantController')->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));
    $app->delete('/tenants', function (Request $request, Response $response) {
        return $request->getAttribute('tenantController')->delete($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

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
    $app->post('/import', function (Request $request, Response $response) {
        return $request->getAttribute('importController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
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
        return $request
            ->getAttribute('backupController')
            ->download($request->withAttribute('name', $args['name']), $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->delete('/backups/{name}', function (Request $request, Response $response, array $args) {
        return $request
            ->getAttribute('backupController')
            ->delete($request->withAttribute('name', $args['name']), $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/qr.png', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->image($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/qr.pdf', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->pdf($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/invites.pdf', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->pdfAll($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/logo.png', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->get($request->withAttribute('ext', 'png'), $response);
    });
    $app->post('/logo.png', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/logo.webp', function (Request $request, Response $response) {
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

    $app->get('/database', function (Request $request, Response $response) {
        $uri = $request->getUri();
        $location = 'https://adminer.' . $uri->getHost();
        return $response->withHeader('Location', $location)->withStatus(302);
    })->add(new RoleAuthMiddleware('admin'));
};
