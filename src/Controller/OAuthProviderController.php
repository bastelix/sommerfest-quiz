<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OAuthProviderFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class OAuthProviderController
{
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $name = (string) ($args['name'] ?? '');

        if (!OAuthProviderFactory::isEnabled($name)) {
            return $response->withStatus(404);
        }

        $provider = OAuthProviderFactory::create($name);

        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile'],
        ]);

        $_SESSION['oauth_state'] = $provider->getState();
        $_SESSION['oauth_provider'] = $name;

        return $response->withHeader('Location', $authUrl)->withStatus(302);
    }
}
