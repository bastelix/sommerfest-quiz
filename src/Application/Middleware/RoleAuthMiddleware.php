<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Database;
use App\Service\NamespaceResolver;
use App\Service\UserService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;

/**
 * Middleware ensuring the user has one of the allowed roles.
 */
class RoleAuthMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private array $roles;

    public function __construct(string ...$roles) {
        $this->roles = $roles;
    }

    public function process(Request $request, RequestHandler $handler): Response {
        if ($this->roles === []) {
            return $handler->handle($request);
        }
        $role = $_SESSION['user']['role'] ?? null;
        if ($role === null || !in_array($role, $this->roles, true)) {
            if ($this->isApiRequest($request)) {
                return $this->jsonErrorResponse('unauthorized', 401);
            }

            $response = new SlimResponse();
            $base = RouteContext::fromRequest($request)->getBasePath();

            return $response->withHeader('Location', $base . '/login')->withStatus(302);
        }

        $activeNamespace = $_SESSION['user']['active_namespace'] ?? null;
        if (is_string($activeNamespace) && $activeNamespace !== '') {
            $request = $request->withAttribute('active_namespace', $activeNamespace);
            if (
                $request->getAttribute('namespace') === null
                && $request->getAttribute('pageNamespace') === null
                && $request->getAttribute('legalPageNamespace') === null
            ) {
                $request = $request->withAttribute('namespace', $activeNamespace);
            }
        }

        $namespaceContext = (new NamespaceResolver())->resolve($request);
        if (!$this->userHasNamespace($request, $namespaceContext->getNamespace())) {
            if ($this->isApiRequest($request)) {
                return $this->jsonErrorResponse('forbidden', 403);
            }

            return (new SlimResponse())->withStatus(403);
        }

        return $handler->handle($request);
    }

    private function isApiRequest(Request $request): bool {
        $accept = $request->getHeaderLine('Accept');
        $xhr = $request->getHeaderLine('X-Requested-With');
        $path = $request->getUri()->getPath();
        $base = RouteContext::fromRequest($request)->getBasePath();

        return str_starts_with($path, $base . '/api/')
            || str_contains($accept, 'application/json')
            || $xhr === 'fetch';
    }

    private function jsonErrorResponse(string $error, int $status): Response {
        $response = new SlimResponse($status);
        $response->getBody()->write(json_encode(['error' => $error]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function userHasNamespace(Request $request, string $namespace): bool {
        $userId = $_SESSION['user']['id'] ?? null;
        if ($userId === null) {
            return false;
        }

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        $userService = new UserService($pdo);
        $record = $userService->getById((int) $userId);
        if ($record === null) {
            return false;
        }

        foreach ($record['namespaces'] as $entry) {
            $candidate = strtolower(trim((string) ($entry['namespace'] ?? '')));
            if ($candidate !== '' && $candidate === $namespace) {
                return true;
            }
        }

        return false;
    }
}
