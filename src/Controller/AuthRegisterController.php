<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OAuthProviderFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class AuthRegisterController
{
    public function show(Request $request, Response $response): Response
    {
        $base = RouteContext::fromRequest($request)->getBasePath();

        if (isset($_SESSION['account_id'])) {
            // Already logged in — go to return URL or account page
            $returnUrl = $_SESSION['auth_return_url'] ?? null;
            unset($_SESSION['auth_return_url']);
            $target = is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : $base . '/account/subscriptions';

            return $response->withHeader('Location', $target)->withStatus(302);
        }

        // Preserve plan/app from query params (from pricing page link)
        $params = $request->getQueryParams();
        if (isset($params['plan']) || isset($params['app'])) {
            $_SESSION['auth_register'] = [
                'plan' => (string) ($params['plan'] ?? ''),
                'app' => (string) ($params['app'] ?? ''),
            ];
        }

        $view = Twig::fromRequest($request);

        return $view->render($response, 'auth/register.twig', [
            'providers' => OAuthProviderFactory::enabledProviders(),
            'basePath' => $base,
        ]);
    }
}
