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
use App\Application\Middleware\AdminAuthMiddleware;
use App\Service\ConfigService;
use App\Service\CatalogService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\PhotoConsentService;
use App\Controller\ResultController;
use App\Controller\TeamController;
use App\Controller\PasswordController;
use App\Controller\ImportController;
use App\Controller\ExportController;
use App\Controller\QrController;
use App\Controller\LogoController;
use App\Controller\SummaryController;
use App\Controller\EvidenceController;
use App\Controller\BackupController;

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
require_once __DIR__ . '/Controller/BackupController.php';

use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;

return function (\Slim\App $app) {
    $pdo = Database::connectFromEnv();
    Migrator::migrate($pdo, __DIR__ . '/../migrations');
    $configService = new ConfigService($pdo);
    $catalogService = new CatalogService($pdo);
    $resultService = new ResultService($pdo);
    $teamService = new TeamService($pdo);
    $consentService = new PhotoConsentService($pdo);

    $configController = new ConfigController($configService);
    $catalogController = new CatalogController($catalogService);
    $resultController = new ResultController(
        $resultService,
        $configService,
        $teamService,
        __DIR__ . '/../data/photos'
    );
    $teamController = new TeamController($teamService);
    $passwordController = new PasswordController($configService);
    $qrController = new QrController($configService, $teamService);
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
    $app->get('/admin', AdminController::class)->add(new AdminAuthMiddleware());
    $app->get('/admin/kataloge', AdminCatalogController::class)->add(new AdminAuthMiddleware());
    $app->get('/results', [$resultController, 'page']);
    $app->get('/results.json', [$resultController, 'get']);
    $app->get('/question-results.json', [$resultController, 'getQuestions']);
    $app->get('/results/download', [$resultController, 'download']);
    $app->get('/results.pdf', [$resultController, 'pdf']);
    $app->post('/results', [$resultController, 'post']);
    $app->delete('/results', [$resultController, 'delete']);
    $app->get('/config.json', [$configController, 'get']);
    $app->post('/config.json', [$configController, 'post']);
    $app->get('/kataloge/{file}', [$catalogController, 'get']);
    $app->post('/kataloge/{file}', [$catalogController, 'post']);
    $app->delete('/kataloge/{file}/{index}', [$catalogController, 'deleteQuestion']);
    $app->put('/kataloge/{file}', [$catalogController, 'create']);
    $app->delete('/kataloge/{file}', [$catalogController, 'delete']);

    $app->get('/teams.json', [$teamController, 'get']);
    $app->post('/teams.json', [$teamController, 'post']);
    $app->post('/password', [$passwordController, 'post']);
    $app->post('/import', [$importController, 'post']);
    $app->post('/import/{name}', [$importController, 'import']);
    $app->post('/export', [$exportController, 'post']);
    $app->get('/backups', [$backupController, 'list']);
    $app->get('/backups/{name}/download', [$backupController, 'download']);
    $app->delete('/backups/{name}', [$backupController, 'delete']);
    $app->get('/qr.png', [$qrController, 'image']);
    $app->get('/qr.pdf', [$qrController, 'pdf']);
    $app->get('/invites.pdf', [$qrController, 'pdfAll']);
    $app->get('/logo.png', [$logoController, 'get'])->setArgument('ext', 'png');
    $app->post('/logo.png', [$logoController, 'post']);
    $app->get('/logo.webp', [$logoController, 'get'])->setArgument('ext', 'webp');
    $app->post('/logo.webp', [$logoController, 'post']);
    $app->post('/photos', [$evidenceController, 'post']);
    $app->get('/photo/{team}/{file}', [$evidenceController, 'get']);
    $app->get('/summary', $summaryController);

    $app->get('/database', function (Request $request, Response $response) {
        $uri = $request->getUri();
        $location = 'https://adminer.' . $uri->getHost();
        return $response->withHeader('Location', $location)->withStatus(302);
    });
};
