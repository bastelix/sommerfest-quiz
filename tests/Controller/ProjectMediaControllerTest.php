<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ProjectMediaController;
use App\Domain\Roles;
use App\Service\ConfigService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\Uri;

class ProjectMediaControllerTest extends TestCase
{
    private string $uploadsDir;

    protected function setUp(): void
    {
        $this->uploadsDir = __DIR__ . '/../../data/uploads/projects/test-ns';
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0775, true);
        }
        file_put_contents($this->uploadsDir . '/sample.png', 'fake-image');

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (is_file($this->uploadsDir . '/sample.png')) {
            unlink($this->uploadsDir . '/sample.png');
        }
        $meta = $this->uploadsDir . '/.media-metadata.json';
        if (is_file($meta)) {
            unlink($meta);
        }
        if (is_dir($this->uploadsDir)) {
            @rmdir($this->uploadsDir);
        }

        $_SESSION = [];

        parent::tearDown();
    }

    public function testUnauthenticatedRequestReturns403(): void
    {
        $controller = $this->createController();
        $request = $this->buildRequest('test-ns', 'sample.png');
        $response = $controller->get($request, (new ResponseFactory())->createResponse());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testUserWithMatchingNamespaceCanAccess(): void
    {
        $_SESSION['user'] = [
            'role' => Roles::CUSTOMER,
            'namespaces' => [['namespace' => 'test-ns']],
            'active_namespace' => 'test-ns',
        ];

        $controller = $this->createController();
        $request = $this->buildRequest('test-ns', 'sample.png');
        $response = $controller->get($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUserWithoutMatchingNamespaceGets403(): void
    {
        $_SESSION['user'] = [
            'role' => Roles::CUSTOMER,
            'namespaces' => [['namespace' => 'other-ns']],
            'active_namespace' => 'other-ns',
        ];

        $controller = $this->createController();
        $request = $this->buildRequest('test-ns', 'sample.png');
        $response = $controller->get($request, (new ResponseFactory())->createResponse());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAdminCanAccessAnyNamespace(): void
    {
        $_SESSION['user'] = [
            'role' => Roles::ADMIN,
            'active_namespace' => 'admin-ns',
        ];

        $controller = $this->createController();
        $request = $this->buildRequest('test-ns', 'sample.png');
        $response = $controller->get($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEmptyRoleDeniesAccess(): void
    {
        $_SESSION['user'] = [
            'role' => '',
        ];

        $controller = $this->createController();
        $request = $this->buildRequest('test-ns', 'sample.png');
        $response = $controller->get($request, (new ResponseFactory())->createResponse());

        $this->assertSame(403, $response->getStatusCode());
    }

    private function createController(): ProjectMediaController
    {
        $config = $this->createMock(ConfigService::class);
        $config->method('getProjectUploadsDir')
            ->willReturnCallback(function (string $ns): string {
                $dir = __DIR__ . '/../../data/uploads/projects/' . $ns;
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                return $dir;
            });

        return new ProjectMediaController($config);
    }

    private function buildRequest(string $namespace, string $file): SlimRequest
    {
        $uri = new Uri('https', 'example.com', 443, '/uploads/projects/' . $namespace . '/' . $file);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);
        $headers = new Headers();
        $request = new SlimRequest('GET', $uri, $headers, [], [], $stream);

        return $request
            ->withAttribute('requestedNamespace', $namespace)
            ->withAttribute('file', $file);
    }
}
