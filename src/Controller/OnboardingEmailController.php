<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\EmailConfirmationService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\SettingsService;
use App\Service\NamespaceResolver;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Handle email confirmation during onboarding.
 */
class OnboardingEmailController
{
    private EmailConfirmationService $service;

    public function __construct(EmailConfirmationService $service) {
        $this->service = $service;
    }

    /**
     * Accept email and send confirmation link.
     */
    public function request(Request $request, Response $response): Response {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $response->withStatus(400);
        }

        $_SESSION['onboarding']['email'] = $email;

        $token = $this->service->createToken($email);
        $base = rtrim(RouteContext::fromRequest($request)->getBasePath(), '/');
        $uri = $request->getUri()
            ->withPath($base . '/onboarding/email/confirm')
            ->withQuery('token=' . urlencode($token));

        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $manager = $request->getAttribute('mailProviderManager');
        if (!$manager instanceof MailProviderManager) {
            $pdo = Database::connectFromEnv();
            $manager = new MailProviderManager(new SettingsService($pdo), [], null, $namespace);
        }

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!$manager->isConfigured()) {
                return $response->withStatus(503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $manager);
        }
        $mailer->sendDoubleOptIn($email, (string) $uri);

        return $response->withStatus(204);
    }

    /**
     * Confirm email via token and redirect back to onboarding.
     */
    public function confirm(Request $request, Response $response): Response {
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        if ($token === '') {
            return $response->withStatus(400);
        }

        $email = $this->service->confirmToken($token);
        if ($email === null) {
            return $response->withStatus(400);
        }

        $_SESSION['onboarding']['email'] = $email;
        $_SESSION['onboarding']['verified'] = true;

        $base = rtrim(RouteContext::fromRequest($request)->getBasePath(), '/');
        $uri = $request->getUri()
            ->withPath($base . '/onboarding')
            ->withQuery(http_build_query([
                'email' => $email,
                'verified' => 1,
                'step' => 'domain',
            ]));

        return $response->withHeader('Location', (string) $uri)->withStatus(302);
    }

    /**
     * Return 204 if email is confirmed, 404 otherwise.
     */
    public function status(Request $request, Response $response): Response {
        $email = (string) ($request->getQueryParams()['email'] ?? '');
        if ($email === '') {
            return $response->withStatus(400);
        }

        return $this->service->isConfirmed($email)
            ? $response->withStatus(204)
            : $response->withStatus(404);
    }
}
