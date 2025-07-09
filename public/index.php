<?php

$autoloader = __DIR__ . '/../vendor/autoload.php';
if (!is_readable($autoloader)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Autoloader not found. Please run 'composer install'.\n";
    exit(1);
}
require $autoloader;

// Load environment variables from .env if available
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    $vars = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($vars)) {
        foreach ($vars as $key => $value) {
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}

use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Application\Middleware\SessionMiddleware;
use App\Twig\UikitExtension;

$settings = require __DIR__ . '/../config/settings.php';
$app = \Slim\Factory\AppFactory::create();

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$twig->addExtension(new UikitExtension());
$app->add(TwigMiddleware::create($app, $twig));
$app->add(new SessionMiddleware());

$app->addErrorMiddleware($settings['displayErrorDetails'], true, true);
(require __DIR__ . '/../src/routes.php')($app);
$app->run();
