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
use App\Infrastructure\Database;
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
                $pdo = Database::connectFromEnv();
                Migrator::migrate($pdo, __DIR__ . '/../migrations');
            }
        }

        // Load settings
        $settings = require __DIR__ . '/../config/settings.php';

        // Instantiate the app
        $app = AppFactory::create();

        $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
        $twig->addExtension(new \App\Twig\UikitExtension());
        $basePath = getenv('BASE_PATH') ?: '';
        $twig->getEnvironment()->addGlobal('basePath', rtrim($basePath, '/'));
        $app->setBasePath($basePath);
        $app->add(TwigMiddleware::create($app, $twig));
        $app->add(new SessionMiddleware());

        // Register error middleware
        $app->addErrorMiddleware($settings['displayErrorDetails'], true, true);

        // Register routes
        $routes = require __DIR__ . '/../src/routes.php';
        $routes($app);

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
        array $cookies = [],
        array $serverParams = []
    ): Request {
        $uri = new Uri('', '', 80, $path);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    }

    protected function createMigratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Migrator::migrate($pdo, __DIR__ . '/../migrations');
        return $pdo;
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDbs as $db) {
            if (is_string($db) && file_exists($db)) {
                @unlink($db);
            }
        }
        $this->tmpDbs = [];
        parent::tearDown();
    }
}
