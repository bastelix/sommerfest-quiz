<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class AuthLogoutController
{
    public function __invoke(Request $request, Response $response): Response
    {
        unset($_SESSION['account_id'], $_SESSION['account_email']);

        $base = RouteContext::fromRequest($request)->getBasePath();

        return $response->withHeader('Location', $base . '/')->withStatus(302);
    }
}
