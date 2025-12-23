<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Service\NamespaceAccessService;
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

        if ($role === Roles::ADMIN) {
            return $handler->handle($request);
        }

        $accessService = new NamespaceAccessService();
        if (!isset($_SESSION['user']['namespaces'])) {
            $this->resolveUserNamespaces($request);
        }
        $allowedNamespaces = $accessService->resolveAllowedNamespaces($role);
        if (is_string($activeNamespace) && $activeNamespace !== '') {
            if (!in_array(strtolower(trim($activeNamespace)), $allowedNamespaces, true)) {
                $this->resolveUserNamespaces($request, true);
                $allowedNamespaces = $accessService->resolveAllowedNamespaces($role);
            }
            if (!in_array(strtolower(trim($activeNamespace)), $allowedNamespaces, true)) {
                if ($this->isApiRequest($request)) {
                    return $this->jsonErrorResponse('forbidden', 403);
                }

                return (new SlimResponse())->withStatus(403);
            }
        }

        $namespaceContext = (new NamespaceResolver())->resolve($request);
        if (!in_array(strtolower(trim($namespaceContext->getNamespace())), $allowedNamespaces, true)) {
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

    /**
     * @return list<array{namespace:string,is_default:bool}>
     */
    private function resolveUserNamespaces(Request $request, bool $forceReload = false): array
    {
        if (!$forceReload) {
            $existing = $_SESSION['user']['namespaces'] ?? null;
            if (is_array($existing)) {
                return $existing;
            }
        }

        $userId = $_SESSION['user']['id'] ?? null;
        if ($userId === null) {
            return [];
        }

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        $userService = new UserService($pdo);
        $record = $userService->getById((int) $userId);
        if ($record === null) {
            return [];
        }

        $namespaces = $record['namespaces'];
        $_SESSION['user']['namespaces'] = $namespaces;

        return $namespaces;
    }

}
