<?php

declare(strict_types=1);

$autoload = null;
$currentDir = __DIR__;
while ($currentDir !== '' && $currentDir !== DIRECTORY_SEPARATOR) {
    $candidate = $currentDir . '/vendor/autoload.php';
    if (is_readable($candidate)) {
        $autoload = $candidate;
        break;
    }

    $candidate = $currentDir . '/autoload.php';
    if (is_readable($candidate)) {
        $autoload = $candidate;
        break;
    }

    $parentDir = dirname($currentDir);
    if ($parentDir === $currentDir) {
        break;
    }

    $currentDir = $parentDir;
}

if ($autoload === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Autoloader not found. Please run 'composer install'.\n";
    exit(1);
}

require $autoload;

// Load environment variables from .env if available
\App\Support\EnvLoader::loadAndSet(__DIR__ . '/../.env');

use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Middleware\SessionMiddleware;
use App\Application\Middleware\DomainMiddleware;
use App\Application\Middleware\ProxyMiddleware;
use App\Application\Middleware\UrlMiddleware;
use App\Application\RateLimiting\RateLimitStoreFactory;
use App\Twig\DateTimeFormatExtension;
use App\Twig\UikitExtension;
use App\Twig\TranslationExtension;
use App\Service\TranslationService;
use App\Service\StripeService;
use App\Service\MarketingDomainProvider;
use App\Support\DomainNameHelper;
use App\Infrastructure\Database;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\NullLogger;

// set up logger and fall back to NullLogger if Monolog is unavailable
$logger = class_exists(Logger::class) ? new Logger('app') : new NullLogger();
if ($logger instanceof Logger) {
    $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log'));
}

// verify Stripe configuration before starting the app
if (!StripeService::isConfigured()['ok']) {
    $logger->error('Stripe configuration missing');
}

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
$twig->addExtension(new DateTimeFormatExtension());
$twig->addExtension(new TranslationExtension($translator));
$twig->getEnvironment()->addGlobal('basePath', $basePath);
$twig->getEnvironment()->addGlobal('pageEditorDriver', 'tiptap');
$twig->getEnvironment()->addGlobal('displayErrorDetails', (bool) ($settings['displayErrorDetails'] ?? false));
$marketingDomainProvider = new MarketingDomainProvider(
    static function (): \PDO {
        return Database::connectFromEnv();
    }
);
DomainNameHelper::setMarketingDomainProvider($marketingDomainProvider);
$app->add(TwigMiddleware::create($app, $twig));
$app->add(new UrlMiddleware($twig));
$app->add(new DomainMiddleware($marketingDomainProvider));
$app->add(new ProxyMiddleware());

RateLimitMiddleware::setPersistentStore(RateLimitStoreFactory::createDefault());

(require __DIR__ . '/../src/routes.php')($app, $translator);

$app->add(new SessionMiddleware());
$errorMiddleware = new \App\Application\Middleware\ErrorMiddleware(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    (bool)($settings['displayErrorDetails'] ?? false),
    true,
    true,
    $logger
);
$app->add($errorMiddleware);
$app->run();
