<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Middleware\SessionMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class SessionMiddlewareTest extends TestCase
{
    private string $originalSessionSavePath;

    /** @var list<string> */
    private array $pathsToCleanup = [];

    protected function setUp(): void {
        parent::setUp();
        $this->originalSessionSavePath = session_save_path();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        session_set_cookie_params(['domain' => '']);
        unset($_ENV['MAIN_DOMAIN']);
        putenv('MAIN_DOMAIN');
        unset($_ENV['SESSION_COOKIE_SECURE']);
        putenv('SESSION_COOKIE_SECURE');
        unset($_ENV['SESSION_SAVE_PATH']);
        putenv('SESSION_SAVE_PATH');
        ini_set('session.save_path', $this->originalSessionSavePath);
        session_save_path($this->originalSessionSavePath);
    }

    protected function tearDown(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        session_set_cookie_params(['domain' => '']);
        unset($_ENV['SESSION_COOKIE_SECURE']);
        putenv('SESSION_COOKIE_SECURE');
        unset($_ENV['SESSION_SAVE_PATH']);
        putenv('SESSION_SAVE_PATH');
        ini_set('session.save_path', $this->originalSessionSavePath);
        session_save_path($this->originalSessionSavePath);

        foreach ($this->pathsToCleanup as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->pathsToCleanup = [];

        parent::tearDown();
    }

    private function createRequest(string $host): Request {
        $uri = new Uri('http', $host, 80, '/');
        $headers = new Headers();
        $stream = (new StreamFactory())->createStream();
        return new SlimRequest('GET', $uri, $headers, [], [], $stream);
    }

    private function handle(Request $request): void {
        $handler = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response {
                return new Response();
            }
        };
        $middleware = new SessionMiddleware();
        $middleware->process($request, $handler);
    }

    public function testUsesSessionSavePathFromEnv(): void {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'session_middleware_' . bin2hex(random_bytes(5));
        $this->pathsToCleanup[] = $tempDir;

        putenv('SESSION_SAVE_PATH=' . $tempDir);
        $_ENV['SESSION_SAVE_PATH'] = $tempDir;

        $request = $this->createRequest('example.com');
        $this->handle($request);

        $this->assertSame($tempDir, $this->normalizedSessionPath(session_save_path()));
        $this->assertDirectoryExists($tempDir);
        $this->assertTrue(is_writable($tempDir));
    }

    public function testFallsBackToProjectSessionDirectoryWhenUnset(): void {
        unset($_ENV['SESSION_SAVE_PATH']);
        putenv('SESSION_SAVE_PATH');
        session_save_path('');
        ini_set('session.save_path', '');

        $expected = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sessions';
        $existed = is_dir($expected);
        if (!$existed) {
            $this->pathsToCleanup[] = $expected;
        }

        $request = $this->createRequest('example.com');
        $this->handle($request);

        $this->assertSame($expected, $this->normalizedSessionPath(session_save_path()));
        $this->assertDirectoryExists($expected);
    }

    public function testSkipsDomainForIpAddress(): void {
        $request = $this->createRequest('127.0.0.1');
        $this->handle($request);
        $params = session_get_cookie_params();
        $this->assertSame('', $params['domain']);
    }

    public function testSkipsDomainForLocalhost(): void {
        $request = $this->createRequest('localhost');
        $this->handle($request);
        $params = session_get_cookie_params();
        $this->assertSame('', $params['domain']);
    }

    public function testUsesHostDomainWhenEnvEmpty(): void {
        $request = $this->createRequest('example.com');
        $this->handle($request);
        $params = session_get_cookie_params();
        $this->assertSame('.example.com', $params['domain']);
    }

    public function testSetsSecureFlagFromForwardedProto(): void {
        $uri = new Uri('http', 'example.com', 80, '/');
        $headers = new Headers(['X-Forwarded-Proto' => ['https']]);
        $stream = (new StreamFactory())->createStream();
        $request = new SlimRequest('GET', $uri, $headers, [], [], $stream);
        $this->handle($request);
        $params = session_get_cookie_params();
        $this->assertTrue($params['secure']);
    }

    public function testSetsSecureFlagFromEnv(): void {
        putenv('SESSION_COOKIE_SECURE=true');
        $_ENV['SESSION_COOKIE_SECURE'] = 'true';
        $request = $this->createRequest('example.com');
        $this->handle($request);
        $params = session_get_cookie_params();
        $this->assertTrue($params['secure']);
    }

    private function normalizedSessionPath(string $path): string {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        if (str_contains($trimmed, ';')) {
            $parts = array_filter(explode(';', $trimmed));
            $last = end($parts);
            if ($last !== false) {
                $trimmed = (string) $last;
            }
        }

        return $trimmed;
    }
}
