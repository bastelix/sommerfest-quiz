<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;

/**
 * Ensure the visitor has a logged-in central account.
 *
 * Checks $_SESSION['account_id']. When missing the current URL is stored
 * in the session as return target and the visitor is redirected to the
 * account registration page.
 */
class AccountAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $accountId = $_SESSION['account_id'] ?? null;

        if ($accountId === null) {
            $uri = $request->getUri();
            $returnUrl = (string) $uri;
            if ($returnUrl !== '') {
                $_SESSION['auth_return_url'] = $returnUrl;
            }

            $base = RouteContext::fromRequest($request)->getBasePath();
            $response = new SlimResponse();

            return $response->withHeader('Location', $base . '/auth/register')->withStatus(302);
        }

        $request = $request->withAttribute('account_id', (int) $accountId);

        return $handler->handle($request);
    }
}
