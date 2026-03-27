<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OAuthProviderFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class AuthLoginController
{
    public function show(Request $request, Response $response): Response
    {
        if (isset($_SESSION['account_id'])) {
            $base = RouteContext::fromRequest($request)->getBasePath();

            return $response->withHeader('Location', $base . '/')->withStatus(302);
        }

        $view = Twig::fromRequest($request);
        $basePath = RouteContext::fromRequest($request)->getBasePath();

        return $view->render($response, 'auth/login.twig', [
            'providers' => OAuthProviderFactory::enabledProviders(),
            'basePath' => $basePath,
        ]);
    }
}
