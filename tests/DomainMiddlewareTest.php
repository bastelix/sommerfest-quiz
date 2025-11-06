<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Middleware\DomainMiddleware;
use App\Service\DomainStartPageService;
use App\Service\MarketingDomainProvider;
use App\Support\DomainNameHelper;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use PDO;

class DomainMiddlewareTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->envBackup = [
            'MAIN_DOMAIN' => getenv('MAIN_DOMAIN'),
            'MARKETING_DOMAINS' => getenv('MARKETING_DOMAINS'),
        ];

        putenv('MAIN_DOMAIN');
        putenv('MARKETING_DOMAINS');
    }

    protected function tearDown(): void
    {
        DomainNameHelper::setMarketingDomainProvider(null);
        $this->restoreEnv('MAIN_DOMAIN', $this->envBackup['MAIN_DOMAIN']);
        $this->restoreEnv('MARKETING_DOMAINS', $this->envBackup['MARKETING_DOMAINS']);

        parent::tearDown();
    }

    public function testWwwHostTreatedAsMain(): void {
        $middleware = new DomainMiddleware($this->createProvider([], 'main-domain.tld'));
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://www.main-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);
        $this->assertSame('main', $handler->request->getAttribute('domainType'));
    }

    public function testAdminHostTreatedAsMain(): void {
        $middleware = new DomainMiddleware($this->createProvider([], 'main-domain.tld'));
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://admin.main-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);
        $this->assertSame('main', $handler->request->getAttribute('domainType'));
    }

    public function testMissingMainDomainReturnsError(): void {
        $middleware = new DomainMiddleware($this->createProvider());
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://foo.test/');
        $request = $request->withHeader('Accept', 'application/json');

        $handler = new class implements RequestHandlerInterface {
            public bool $handled = false;

            public function handle(Request $request): ResponseInterface {
                $this->handled = true;
                return new Response();
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertFalse($handler->handled);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            json_encode(['error' => 'Invalid main domain configuration.']),
            (string) $response->getBody()
        );
    }

    public function testInvalidMainDomainReturnsError(): void {
        $middleware = new DomainMiddleware($this->createProvider([], 'main-domain.tld'));
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://wrong.tld/');
        $request = $request->withHeader('Accept', 'application/json');

        $handler = new class implements RequestHandlerInterface {
            public bool $handled = false;

            public function handle(Request $request): ResponseInterface {
                $this->handled = true;
                return new Response();
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertFalse($handler->handled);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            json_encode(['error' => 'Invalid main domain configuration.']),
            (string) $response->getBody()
        );
    }

    public function testInvalidMainDomainReturnsHtmlError(): void {
        $middleware = new DomainMiddleware($this->createProvider([], 'main-domain.tld'));
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://wrong.tld/');

        $handler = new class implements RequestHandlerInterface {
            public bool $handled = false;

            public function handle(Request $request): ResponseInterface {
                $this->handled = true;
                return new Response();
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertFalse($handler->handled);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertSame('Invalid main domain configuration.', (string) $response->getBody());
    }

    public function testMarketingDomainAllowed(): void {
        $middleware = new DomainMiddleware($this->createProvider(['marketing-domain.tld'], 'main-domain.tld'));
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://marketing-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame('marketing', $handler->request->getAttribute('domainType'));
    }

    public function testMarketingDomainWithWwwAllowed(): void {
        $middleware = new DomainMiddleware($this->createProvider(['marketing-domain.tld'], 'main-domain.tld'));
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', 'https://www.marketing-domain.tld/');

        $handler = new class implements RequestHandlerInterface {
            public ?Request $request = null;

            public function handle(Request $request): ResponseInterface {
                $this->request = $request;
                return new Response();
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame('marketing', $handler->request->getAttribute('domainType'));
    }

    private function restoreEnv(string $variable, mixed $value): void {
        if ($value === false || $value === null) {
            putenv($variable);
            return;
        }

        putenv(sprintf('%s=%s', $variable, $value));
    }

    /**
     * @param list<string> $marketingDomains
     */
    private function createProvider(array $marketingDomains = [], ?string $mainDomain = null): MarketingDomainProvider
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS settings ('
            . 'key TEXT PRIMARY KEY, '
            . 'value TEXT)'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS marketing_domains ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'host TEXT NOT NULL, '
            . 'normalized_host TEXT NOT NULL UNIQUE, '
            . 'label TEXT, '
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
        );

        if ($mainDomain !== null) {
            $stmt = $pdo->prepare('INSERT INTO settings(key, value) VALUES(?, ?)');
            $stmt->execute(['main_domain', $mainDomain]);
        }

        $service = new DomainStartPageService($pdo);
        foreach ($marketingDomains as $domain) {
            $service->createMarketingDomain($domain);
        }

        $provider = new MarketingDomainProvider(static fn (): PDO => $pdo, 0);
        DomainNameHelper::setMarketingDomainProvider($provider);

        return $provider;
    }
}
