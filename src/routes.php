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
require_once __DIR__ . '/Controller/SummaryController.php';
require_once __DIR__ . '/Controller/EvidenceController.php';
require_once __DIR__ . '/Controller/ExportController.php';
require_once __DIR__ . '/Controller/EventController.php';
require_once __DIR__ . '/Controller/BackupController.php';
require_once __DIR__ . '/Controller/UserController.php';
require_once __DIR__ . '/Controller/TenantController.php';

use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;

return function (\Slim\App $app) {
    $pdo = Database::connectFromEnv();
    Migrator::migrate($pdo, __DIR__ . '/../migrations');
    $configService = new ConfigService($pdo);
    $catalogService = new CatalogService($pdo, $configService);
    $resultService = new ResultService($pdo, $configService);
    $teamService = new TeamService($pdo, $configService);
    $consentService = new PhotoConsentService($pdo, $configService);
    $eventService = new EventService($pdo);
    $tenantService = new TenantService();
    $userService = new \App\Service\UserService($pdo);

    $configController = new ConfigController($configService);
    $catalogController = new CatalogController($catalogService);
    $resultController = new ResultController(
        $resultService,
        $configService,
        $teamService,
        $catalogService,
        __DIR__ . '/../data/photos',
        $eventService
    );
    $teamController = new TeamController($teamService);
    $eventController = new EventController($eventService);
    $tenantController = new TenantController($tenantService);
    $passwordController = new PasswordController($userService);
    $userController = new UserController($userService);
    $qrController = new QrController($configService, $teamService, $eventService);
    $logoController = new LogoController($configService);
    $summaryController = new SummaryController($configService);
    $importController = new ImportController(
        $catalogService,
        $configService,
        $resultService,
        $teamService,
        $consentService,
        __DIR__ . '/../data',
        __DIR__ . '/../backup'
    );
    $exportController = new ExportController(
        $configService,
        $catalogService,
        $resultService,
        $teamService,
        $consentService,
        __DIR__ . '/../data',
        __DIR__ . '/../backup'
    );
    $backupController = new BackupController(__DIR__ . '/../backup');
    $evidenceController = new EvidenceController(
        $resultService,
        $consentService,
        new NullLogger(),
        __DIR__ . '/../data/photos'
    );

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
    $app->get('/admin/kataloge', AdminCatalogController::class)->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->get('/results', [$resultController, 'page'])->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));
    $app->get('/results.json', [$resultController, 'get'])->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));
    $app->get('/question-results.json', [$resultController, 'getQuestions'])->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));
    $app->get('/results/download', [$resultController, 'download'])->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));
    $app->get('/results.pdf', [$resultController, 'pdf'])->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));
    $app->post('/results', [$resultController, 'post']);
    $app->delete('/results', [$resultController, 'delete'])->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/config.json', [$configController, 'get']);
    $app->post('/config.json', [$configController, 'post'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));
    $app->get('/kataloge/{file}', [$catalogController, 'get'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->post('/kataloge/{file}', [$catalogController, 'post'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->delete('/kataloge/{file}/{index}', [$catalogController, 'deleteQuestion'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->put('/kataloge/{file}', [$catalogController, 'create'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->delete('/kataloge/{file}', [$catalogController, 'delete'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->get('/events.json', [$eventController, 'get'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));
    $app->post('/events.json', [$eventController, 'post'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));

    $app->post('/tenants', [$tenantController, 'create'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->delete('/tenants', [$tenantController, 'delete'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/teams.json', [$teamController, 'get']);
    $app->post('/teams.json', [$teamController, 'post'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::TEAM_MANAGER));
    $app->get('/users.json', [$userController, 'get'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->post('/users.json', [$userController, 'post'])
        ->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->post('/password', [$passwordController, 'post'])->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->post('/import', [$importController, 'post'])->add(new RoleAuthMiddleware('admin'));
    $app->post('/import/{name}', [$importController, 'import'])->add(new RoleAuthMiddleware('admin'));
    $app->post('/export', [$exportController, 'post'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/backups', [$backupController, 'list'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/backups/{name}/download', [$backupController, 'download'])->add(new RoleAuthMiddleware('admin'));
    $app->delete('/backups/{name}', [$backupController, 'delete'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/qr.png', [$qrController, 'image'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/qr.pdf', [$qrController, 'pdf'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/invites.pdf', [$qrController, 'pdfAll'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/logo.png', [$logoController, 'get'])->setArgument('ext', 'png');
    $app->post('/logo.png', [$logoController, 'post'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/logo.webp', [$logoController, 'get'])->setArgument('ext', 'webp');
    $app->post('/logo.webp', [$logoController, 'post'])->add(new RoleAuthMiddleware('admin'));
    $app->post('/photos', [$evidenceController, 'post'])->add(new RoleAuthMiddleware('admin'));
    $app->post('/photos/rotate', [$evidenceController, 'rotate'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/photo/{team}/{file}', [$evidenceController, 'get'])->add(new RoleAuthMiddleware('admin'));
    $app->get('/summary', $summaryController);

    $app->get('/database', function (Request $request, Response $response) {
        $uri = $request->getUri();
        $location = 'https://adminer.' . $uri->getHost();
        return $response->withHeader('Location', $location)->withStatus(302);
    })->add(new RoleAuthMiddleware('admin'));
};
