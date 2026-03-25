<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\NamespaceSubscriptionService;
use App\Service\StripeService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Customer-facing account page on product domains.
 *
 * Visitors on eforms.cloud/account, calserver.com/account etc. can
 * enter their email to access the Stripe Customer Portal for their
 * subscription (invoices, payment methods, plan changes).
 *
 * No admin login required — authentication is handled by Stripe via
 * the customer email lookup.
 */
final class AccountController
{
    /**
     * GET /account — show email form or redirect to Stripe portal.
     */
    public function show(Request $request, Response $response): Response
    {
        $namespace = (string) ($request->getAttribute('domainNamespace')
            ?? $request->getAttribute('namespace')
            ?? $request->getQueryParams()['namespace']
            ?? 'default');

        $view = Twig::fromRequest($request);

        return $view->render($response, 'account/index.twig', [
            'namespace' => $namespace,
            'basePath' => $request->getAttribute('basePath') ?? '',
            'error' => null,
            'email' => '',
        ]);
    }

    /**
     * POST /account — look up Stripe customer by email and redirect to portal.
     */
    public function login(Request $request, Response $response): Response
    {
        $namespace = (string) ($request->getAttribute('domainNamespace')
            ?? $request->getAttribute('namespace')
            ?? 'default');

        $body = $request->getParsedBody();
        $email = trim((string) ($body['email'] ?? ''));
        $basePath = $request->getAttribute('basePath') ?? '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderWithError($request, $response, $namespace, $email, 'Bitte geben Sie eine gueltige E-Mail-Adresse ein.');
        }

        if (!StripeService::isConfigured()['ok']) {
            return $this->renderWithError($request, $response, $namespace, $email, 'Der Bezahldienst ist derzeit nicht verfuegbar.');
        }

        // Find Stripe customer by email
        try {
            $service = new StripeService();
            $customerId = $service->findCustomerIdByEmail($email);
        } catch (\Throwable) {
            return $this->renderWithError($request, $response, $namespace, $email, 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es spaeter erneut.');
        }

        if ($customerId === null || $customerId === '') {
            return $this->renderWithError($request, $response, $namespace, $email, 'Kein Konto mit dieser E-Mail-Adresse gefunden.');
        }

        // Redirect to Stripe Customer Portal
        $uri = $request->getUri();
        $returnUrl = $uri->getScheme() . '://' . $uri->getHost() . $basePath . '/account';

        try {
            $portalUrl = $service->createBillingPortal($customerId, $returnUrl);
        } catch (\Throwable) {
            return $this->renderWithError($request, $response, $namespace, $email, 'Das Kundenportal konnte nicht geladen werden.');
        }

        return $response->withHeader('Location', $portalUrl)->withStatus(302);
    }

    private function renderWithError(Request $request, Response $response, string $namespace, string $email, string $error): Response
    {
        $view = Twig::fromRequest($request);

        return $view->render($response, 'account/index.twig', [
            'namespace' => $namespace,
            'basePath' => $request->getAttribute('basePath') ?? '',
            'error' => $error,
            'email' => $email,
        ]);
    }
}
