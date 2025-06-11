<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controller\HomeController;
use App\Controller\FaqController;
use App\Controller\DatenschutzController;
use App\Controller\AdminController;
use App\Controller\ConfigController;
use App\Controller\CatalogController;
use App\Service\ConfigService;
use App\Service\CatalogService;

require_once __DIR__ . '/Controller/HomeController.php';
require_once __DIR__ . '/Controller/FaqController.php';
require_once __DIR__ . '/Controller/DatenschutzController.php';
require_once __DIR__ . '/Controller/AdminController.php';
require_once __DIR__ . '/Controller/ConfigController.php';
require_once __DIR__ . '/Controller/CatalogController.php';

return function (\Slim\App $app) {
    $configService = new ConfigService(__DIR__ . '/../public/js/config.js');
    $catalogService = new CatalogService(__DIR__ . '/../kataloge');

    $configController = new ConfigController($configService);
    $catalogController = new CatalogController($catalogService);

    $app->get('/', HomeController::class);
    $app->get('/faq', FaqController::class);
    $app->get('/datenschutz', DatenschutzController::class);
    $app->get('/admin', AdminController::class);
    $app->get('/config.js', [$configController, 'get']);
    $app->post('/config.js', [$configController, 'post']);
    $app->get('/kataloge/{file}', [$catalogController, 'get']);
    $app->post('/kataloge/{file}', [$catalogController, 'post']);
};
