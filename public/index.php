<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Application\Middleware\SessionMiddleware;

$settings = require __DIR__ . '/../config/settings.php';
$app = \Slim\Factory\AppFactory::create();
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath !== '') {
    $app->setBasePath($basePath);
}

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$twig->getEnvironment()->addGlobal('base_path', $basePath);
$app->add(TwigMiddleware::create($app, $twig));
$app->add(new SessionMiddleware());

$app->addErrorMiddleware($settings['displayErrorDetails'], true, true);
(require __DIR__ . '/../src/routes.php')($app);
$app->run();
