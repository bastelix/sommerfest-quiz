<?php

use App\Application\Middleware\McpOriginMiddleware;
use App\Application\Middleware\OAuthTokenAuthMiddleware;
use App\Controller\Api\McpController;
use App\Controller\Api\OAuthController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (\Slim\App $app): void {
    $originMiddleware = new McpOriginMiddleware();

    // OAuth 2.0 Discovery (RFC 8414)
    $app->get('/.well-known/oauth-authorization-server', function (Request $request, Response $response): Response {
        return (new OAuthController())->metadata($request, $response);
    });

    // OAuth 2.0 Dynamic Client Registration (RFC 7591)
    $app->post('/oauth/register', function (Request $request, Response $response): Response {
        return (new OAuthController())->register($request, $response);
    });

    // OAuth 2.0 Authorization Endpoint
    $app->get('/oauth/authorize', function (Request $request, Response $response): Response {
        return (new OAuthController())->authorize($request, $response);
    });

    $app->post('/oauth/authorize', function (Request $request, Response $response): Response {
        return (new OAuthController())->authorizeSubmit($request, $response);
    });

    // OAuth 2.0 Token Endpoint
    $app->post('/oauth/token', function (Request $request, Response $response): Response {
        return (new OAuthController())->token($request, $response);
    });

    // MCP Endpoint — Streamable HTTP transport (POST, GET, DELETE)
    // Origin validation middleware runs on all MCP routes (spec: MUST validate Origin)
    $app->post('/mcp', function (Request $request, Response $response): Response {
        return (new McpController())->handle($request, $response);
    })->add(new OAuthTokenAuthMiddleware())->add($originMiddleware);

    $app->get('/mcp', function (Request $request, Response $response): Response {
        return (new McpController())->handleGet($request, $response);
    })->add($originMiddleware);

    $app->delete('/mcp', function (Request $request, Response $response): Response {
        return (new McpController())->handleDelete($request, $response);
    })->add($originMiddleware);
};
