<?php

declare(strict_types=1);

namespace App\Controller;

use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Display the onboarding wizard for creating a new tenant.
 */
class OnboardingController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $mainDomain = getenv('MAIN_DOMAIN')
            ?: getenv('DOMAIN')
            ?: $request->getUri()->getHost();

        return $view->render($response, 'onboarding.twig', [
            'main_domain' => $mainDomain,
        ]);
    }
}
