<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Application\Middleware\SessionMiddleware;
use App\Application\Middleware\ProxyMiddleware;
use App\Application\Middleware\UrlMiddleware;
use App\Application\Middleware\DomainMiddleware;
use App\Infrastructure\Migrations\Migrator;
use App\Infrastructure\Migrations\MigrationRuntime;
use PDO;
use App\Twig\DateTimeFormatExtension;
use App\Service\MarketingDomainProvider;
use App\Support\DomainNameHelper;

class TestCase extends PHPUnit_TestCase
{
    private ?PDO $pdo = null;

    /**
     * Inject a custom PDO instance for tests that require a specific connection.
     */
    public function setDatabase(PDO $pdo): void {
        $this->pdo = $pdo;
    }

    /**
     * Ensure a database connection is available and return it.
     */
    protected function getDatabase(): PDO {
        if ($this->pdo === null) {
            $this->pdo = $this->createDatabase();
        }

        return $this->pdo;
    }

    /**
     * @return App
     * @throws Exception
     */
    protected function getAppInstance(): App {
        $this->getDatabase();

        // Load settings
        $settings = require __DIR__ . '/../config/settings.php';

        // Instantiate the app
        $app = AppFactory::create();

        $translator = new \App\Service\TranslationService();
        $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
        $twig->addExtension(new \App\Twig\UikitExtension());
        $twig->addExtension(new DateTimeFormatExtension());
        $twig->addExtension(new \App\Twig\TranslationExtension($translator));
        $basePath = getenv('BASE_PATH') ?: '';
        $basePath = '/' . trim($basePath, '/');
        if ($basePath === '/') {
            $basePath = '';
        }
        $twig->getEnvironment()->addGlobal('basePath', $basePath);
        $app->setBasePath($basePath);
        $app->add(TwigMiddleware::create($app, $twig));
        $app->add(new UrlMiddleware($twig));
        $pdo = $this->getDatabase();
        $marketingDomainProvider = new MarketingDomainProvider(static fn (): PDO => $pdo, 0);
        DomainNameHelper::setMarketingDomainProvider($marketingDomainProvider);
        $app->add(new DomainMiddleware($marketingDomainProvider));
        $app->add(new ProxyMiddleware());

        // Register routes
        $routes = require __DIR__ . '/../src/routes.php';
        $routes($app, $translator);

        $app->add(new SessionMiddleware());
        // Register error middleware
        $errorMiddleware = new \App\Application\Middleware\ErrorMiddleware(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            (bool)($settings['displayErrorDetails'] ?? false),
            true,
            false
        );
        $app->add($errorMiddleware);

        return $app;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $headers
     * @param array  $cookies
     * @param array  $serverParams
     * @return Request
     */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'text/html'],
        ?array $cookies = null,
        array $serverParams = []
    ): Request {
        $query = '';
        if (str_contains($path, '?')) {
            [$path, $query] = explode('?', $path, 2);
        }
        $host = getenv('MAIN_DOMAIN');
        if ($host === false || $host === '') {
            $host = 'example.com';
        }

        $uri = new Uri('https', $host, 443, $path, $query);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        $cookies = $cookies ?? [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $cookies[session_name()] = session_id();
        }

        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    }

    /**
     * Create a database with the current schema applied.
     */
    protected function createDatabase(): \PDO {
        $dsn = getenv('POSTGRES_DSN') ?: '';
        $user = getenv('POSTGRES_USER') ?: 'postgres';
        $password = getenv('POSTGRES_PASSWORD') ?: 'postgres';

        if ($dsn === '') {
            $dsn = 'sqlite:file:' . uniqid('test', true) . '?mode=memory&cache=shared';
            $user = '';
            $password = '';
            putenv('POSTGRES_DSN=' . $dsn);
            putenv('POSTGRES_USER=' . $user);
            putenv('POSTGRES_PASSWORD=' . $password);
            $_ENV['POSTGRES_DSN'] = $dsn;
            $_ENV['POSTGRES_USER'] = $user;
            $_ENV['POSTGRES_PASSWORD'] = $password;
        }

        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Migrator::migrate($pdo, __DIR__ . '/../migrations');

        return $pdo;
    }

    protected function tearDown(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_COOKIE = [];
        if (getenv('MAIN_DOMAIN') === false || getenv('MAIN_DOMAIN') === '') {
            putenv('MAIN_DOMAIN=example.com');
            $_ENV['MAIN_DOMAIN'] = 'example.com';
        }
        if (getenv('MARKETING_DOMAINS') === false) {
            putenv('MARKETING_DOMAINS');
            unset($_ENV['MARKETING_DOMAINS']);
        }
        if (getenv('TURNSTILE_SITE_KEY') !== false) {
            putenv('TURNSTILE_SITE_KEY');
        }
        if (array_key_exists('TURNSTILE_SITE_KEY', $_ENV)) {
            unset($_ENV['TURNSTILE_SITE_KEY']);
        }
        if (getenv('TURNSTILE_SECRET_KEY') !== false) {
            putenv('TURNSTILE_SECRET_KEY');
        }
        if (array_key_exists('TURNSTILE_SECRET_KEY', $_ENV)) {
            unset($_ENV['TURNSTILE_SECRET_KEY']);
        }
        if (getenv('RUN_MIGRATIONS_ON_REQUEST') !== false) {
            putenv('RUN_MIGRATIONS_ON_REQUEST');
        }
        if (array_key_exists('RUN_MIGRATIONS_ON_REQUEST', $_ENV)) {
            unset($_ENV['RUN_MIGRATIONS_ON_REQUEST']);
        }
        MigrationRuntime::reset();
        $this->pdo = null;
        parent::tearDown();
    }
}
