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
use App\Controller\QrController;
use App\Controller\LogoController;
use App\Controller\SummaryController;
use App\Controller\EvidenceController;

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

return function (\Slim\App $app) {
    $configService = new ConfigService(
        __DIR__ . '/../data/config.json',
        __DIR__ . '/../config/config.json'
    );
    $catalogService = new CatalogService(__DIR__ . '/../data/kataloge');
    $resultService = new ResultService(__DIR__ . '/../data/results.json');
    $teamService = new TeamService(__DIR__ . '/../data/teams.json');

    $configController = new ConfigController($configService);
    $catalogController = new CatalogController($catalogService);
    $resultController = new ResultController(
        $resultService,
        $configService,
        __DIR__ . '/../data/photos'
    );
    $teamController = new TeamController($teamService);
    $passwordController = new PasswordController($configService);
    $qrController = new QrController();
    $logoController = new LogoController($configService);
    $summaryController = new SummaryController($configService);
    $consentService = new PhotoConsentService(__DIR__ . '/../data/photo_consents.json');
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
    $app->get('/results/download', [$resultController, 'download']);
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
    $app->get('/qr.png', [$qrController, 'image']);
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
