<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Repository\NamespaceApiTokenRepository;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class ApiTokenAuthMiddleware implements MiddlewareInterface
{
    public const ATTR_TOKEN_NAMESPACE = 'apiTokenNamespace';
    public const ATTR_TOKEN_SCOPES = 'apiTokenScopes';
    public const ATTR_TOKEN_ID = 'apiTokenId';

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?NamespaceApiTokenRepository $repo = null,
        private readonly ?string $requiredScope = null
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $auth = trim($request->getHeaderLine('Authorization'));
        if ($auth === '' || stripos($auth, 'bearer ') !== 0) {
            return $this->jsonError(401, 'missing_token');
        }

        $token = trim(substr($auth, 7));
        if ($token === '') {
            return $this->jsonError(401, 'missing_token');
        }

        $pdo = $this->pdo;
        if (!$pdo instanceof PDO) {
            $pdo = RequestDatabase::resolve($request);
        }

        $repo = $this->repo ?? new NamespaceApiTokenRepository($pdo);
        $verified = $repo->verify($token);
        if ($verified === null) {
            return $this->jsonError(403, 'invalid_token');
        }

        $namespace = (string) $verified['namespace'];
        $scopes = $verified['scopes'];
        $tokenId = (int) $verified['tokenId'];

        if ($namespace === '') {
            return $this->jsonError(403, 'invalid_token');
        }

        if ($this->requiredScope !== null) {
            $has = false;
            foreach ($scopes as $scope) {
                if ($scope === $this->requiredScope) {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                return $this->jsonError(403, 'missing_scope');
            }
        }

        $request = $request
            ->withAttribute(self::ATTR_TOKEN_NAMESPACE, $namespace)
            ->withAttribute(self::ATTR_TOKEN_SCOPES, $scopes)
            ->withAttribute(self::ATTR_TOKEN_ID, $tokenId);

        return $handler->handle($request);
    }

    private function jsonError(int $status, string $code): Response
    {
        $res = new SlimResponse($status);
        $res->getBody()->write((string) json_encode(['error' => $code]));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
