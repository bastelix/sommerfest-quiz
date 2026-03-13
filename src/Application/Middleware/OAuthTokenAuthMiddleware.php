<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Repository\OAuthAccessTokenRepository;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class OAuthTokenAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?OAuthAccessTokenRepository $repo = null,
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

        $repo = $this->repo ?? new OAuthAccessTokenRepository($pdo);
        $verified = $repo->verify($token);
        if ($verified === null) {
            return $this->jsonError(401, 'invalid_token');
        }

        $namespace = (string) $verified['namespace'];
        if ($namespace === '') {
            return $this->jsonError(401, 'invalid_token');
        }

        $request = $request
            ->withAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE, $namespace)
            ->withAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_SCOPES, $verified['scopes'])
            ->withAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_ID, $verified['tokenId'])
            ->withAttribute('oauthClientId', $verified['clientId']);

        return $handler->handle($request);
    }

    private function jsonError(int $status, string $code): Response
    {
        $res = new SlimResponse($status);
        $res->getBody()->write((string) json_encode(['error' => $code]));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
