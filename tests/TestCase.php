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
use Slim\Psr7\Uri;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Application\Middleware\SessionMiddleware;
use App\Application\Middleware\ProxyMiddleware;
use App\Infrastructure\Migrations\Migrator;
use PDO;

class TestCase extends PHPUnit_TestCase
{
    /** @var list<string> */
    private array $tmpDbs = [];

    /**
     * @return App
     * @throws Exception
     */
    protected function getAppInstance(): App
    {
        if (getenv('POSTGRES_DSN') === false || getenv('POSTGRES_DSN') === '') {
            $db = tempnam(sys_get_temp_dir(), 'db');
            if ($db !== false) {
                putenv('POSTGRES_DSN=sqlite:' . $db);
                putenv('POSTGRES_USER=');
                putenv('POSTGRES_PASSWORD=');
                $_ENV['POSTGRES_DSN'] = 'sqlite:' . $db;
                $_ENV['POSTGRES_USER'] = '';
                $_ENV['POSTGRES_PASSWORD'] = '';
                $this->tmpDbs[] = $db;
            }
        }

        $dsn = getenv('POSTGRES_DSN');
        if (is_string($dsn) && str_starts_with($dsn, 'sqlite:')) {
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            Migrator::migrate($pdo, __DIR__ . '/../migrations');
            foreach (
                [
                    'imprint_name',
                    'imprint_street',
                    'imprint_zip',
                    'imprint_city',
                    'imprint_email',
                    'custom_limits',
                    'plan_started_at',
                    'plan_expires_at',
                    'stripe_subscription_id',
                    'stripe_price_id',
                    'stripe_status',
                    'stripe_current_period_end',
                ] as $col
            ) {
                try {
                    $pdo->exec('ALTER TABLE tenants ADD COLUMN ' . $col . ' TEXT');
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            try {
                $pdo->exec('ALTER TABLE tenants ADD COLUMN stripe_cancel_at_period_end INTEGER');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Load settings
        $settings = require __DIR__ . '/../config/settings.php';

        // Instantiate the app
        $app = AppFactory::create();

        $translator = new \App\Service\TranslationService();
        $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
        $twig->addExtension(new \App\Twig\UikitExtension());
        $twig->addExtension(new \App\Twig\TranslationExtension($translator));
        $basePath = getenv('BASE_PATH') ?: '';
        $basePath = '/' . trim($basePath, '/');
        if ($basePath === '/') {
            $basePath = '';
        }
        $twig->getEnvironment()->addGlobal('basePath', $basePath);
        $app->setBasePath($basePath);
        $app->add(TwigMiddleware::create($app, $twig));
        $app->add(new SessionMiddleware());
        $app->add(new \App\Application\Middleware\DomainMiddleware());
        $app->add(new \App\Application\Middleware\LanguageMiddleware($translator));
        $app->add(new ProxyMiddleware());

        // Register error middleware
        $errorMiddleware = new \App\Application\Middleware\ErrorMiddleware(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            (bool)($settings['displayErrorDetails'] ?? false),
            true,
            false
        );
        $app->add($errorMiddleware);

        // Register routes
        $routes = require __DIR__ . '/../src/routes.php';
        $routes($app, $translator);

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
        $uri = new Uri('', '', 80, $path, $query);
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
     * Create an in-memory SQLite connection with the current schema applied.
     */
    protected function createDatabase(): \PDO
    {
        $pdo = new class ('sqlite::memory:') extends \PDO {
            public function __construct(string $dsn)
            {
                parent::__construct($dsn);
            }

            public function exec($statement): int|false
            {
                if (preg_match('/^(CREATE|DROP) SCHEMA/i', $statement) || str_starts_with($statement, 'SET search_path')) {
                    return 0;
                }
                return parent::exec($statement);
            }
        };
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, __DIR__ . '/../migrations');
        try {
            $pdo->exec('ALTER TABLE tenants ADD COLUMN custom_limits TEXT');
        } catch (\Throwable $e) {
            // ignore
        }
        return $pdo;
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_COOKIE = [];

        foreach ($this->tmpDbs as $db) {
            if (is_string($db) && file_exists($db)) {
                @unlink($db);
            }
        }
        $this->tmpDbs = [];
        parent::tearDown();
    }
}
