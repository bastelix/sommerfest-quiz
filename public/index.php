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
use App\Application\Middleware\DomainMiddleware;
use App\Twig\UikitExtension;
use App\Twig\TranslationExtension;
use App\Service\TranslationService;

$settings = require __DIR__ . '/../config/settings.php';
$app = \Slim\Factory\AppFactory::create();
$basePath = getenv('BASE_PATH') ?: '';
$basePath = '/' . trim($basePath, '/');
if ($basePath === '/') {
    $basePath = '';
}
$app->setBasePath($basePath);

$translator = new TranslationService();
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$twig->addExtension(new UikitExtension());
$twig->addExtension(new TranslationExtension($translator));
$twig->getEnvironment()->addGlobal('basePath', $basePath);
$app->add(TwigMiddleware::create($app, $twig));
$app->add(new SessionMiddleware());
$app->add(new DomainMiddleware());

$errorMiddleware = new \App\Application\Middleware\ErrorMiddleware(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    (bool)($settings['displayErrorDetails'] ?? false),
    true,
    false
);
$app->add($errorMiddleware);
(require __DIR__ . '/../src/routes.php')($app, $translator);
$app->run();
