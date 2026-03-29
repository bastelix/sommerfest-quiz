<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Infrastructure\Database;
use App\Service\NamespaceResolver;
use App\Service\ProjectSettingsService;
use App\Service\TicketService;
use App\Service\TurnstileConfig;
use App\Service\TurnstileVerificationService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use function is_array;
use function is_string;
use function trim;

final class TicketSubmitController
{
    public function showForm(Request $request, Response $response): Response
    {
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $settings = (new ProjectSettingsService())->getTicketSettings($namespace);

        if (!$settings['ticket_public_submission']) {
            $isLoggedIn = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] !== '';
            if (!$isLoggedIn) {
                $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
                return $response
                    ->withHeader('Location', $basePath . '/admin')
                    ->withStatus(302);
            }
        }

        $csrfToken = $this->ensureCsrfToken();
        $turnstileConfig = $request->getAttribute('turnstileConfig');
        if (!$turnstileConfig instanceof TurnstileConfig) {
            $turnstileConfig = TurnstileConfig::fromEnv();
        }

        return Twig::fromRequest($request)->render($response, 'marketing/ticket_submit.twig', [
            'csrfToken' => $csrfToken,
            'turnstileEnabled' => $turnstileConfig->isEnabled(),
            'turnstileSiteKey' => $turnstileConfig->isEnabled() ? $turnstileConfig->getSiteKey() : '',
            'error' => null,
        ]);
    }

    public function submit(Request $request, Response $response): Response
    {
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $settings = (new ProjectSettingsService())->getTicketSettings($namespace);

        if (!$settings['ticket_public_submission']) {
            $isLoggedIn = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] !== '';
            if (!$isLoggedIn) {
                return $this->json($response, ['error' => 'Anmeldung erforderlich'], 403);
            }
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $data = json_decode((string) $body, true);
        }
        if (!is_array($data)) {
            return $this->renderFormWithError($request, $response, 'Ungültige Anfrage.');
        }

        // Honeypot
        $honeypot = trim((string) ($data['company'] ?? ''));
        if ($honeypot !== '') {
            return Twig::fromRequest($request)->render($response, 'marketing/ticket_submitted.twig', [
                'ticketId' => 0,
            ]);
        }

        // Turnstile CAPTCHA
        $turnstileConfig = $request->getAttribute('turnstileConfig');
        if (!$turnstileConfig instanceof TurnstileConfig) {
            $turnstileConfig = TurnstileConfig::fromEnv();
        }
        if ($turnstileConfig->isEnabled()) {
            $turnstileVerifier = $request->getAttribute('turnstileVerifier');
            if (!$turnstileVerifier instanceof TurnstileVerificationService) {
                $turnstileVerifier = new TurnstileVerificationService($turnstileConfig);
            }
            $token = (string) ($data['cf-turnstile-response'] ?? '');
            $remoteIp = $request->getServerParams()['REMOTE_ADDR'] ?? null;
            $ip = is_string($remoteIp) ? $remoteIp : null;
            if ($token === '' || !$turnstileVerifier->verify($token, $ip)) {
                return $this->renderFormWithError($request, $response, 'CAPTCHA-Prüfung fehlgeschlagen. Bitte versuchen Sie es erneut.');
            }
        }

        // Extract and validate fields
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $type = trim((string) ($data['type'] ?? 'task'));
        $priority = trim((string) ($data['priority'] ?? 'normal'));

        if ($name === '' || $title === '') {
            return $this->renderFormWithError($request, $response, 'Name und Titel sind Pflichtfelder.', $data);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderFormWithError($request, $response, 'Bitte geben Sie eine gültige E-Mail-Adresse ein.', $data);
        }

        // Don't allow critical priority from public form
        if ($priority === 'critical') {
            $priority = 'high';
        }

        // Build description with contact info
        $fullDescription = $description;
        if ($email !== '') {
            $fullDescription = "Kontakt: {$name} <{$email}>\n\n" . $fullDescription;
        }

        try {
            $ticket = (new TicketService())->create(
                $namespace,
                $title,
                $fullDescription,
                $type,
                $priority,
                null,
                null,
                null,
                [],
                null,
                $name,
            );
        } catch (\Throwable $e) {
            return $this->renderFormWithError($request, $response, 'Ticket konnte nicht erstellt werden. Bitte versuchen Sie es erneut.', $data);
        }

        return Twig::fromRequest($request)->render($response, 'marketing/ticket_submitted.twig', [
            'ticketId' => $ticket->getId(),
        ]);
    }

    private function renderFormWithError(Request $request, Response $response, string $error, ?array $data = null): Response
    {
        $turnstileConfig = $request->getAttribute('turnstileConfig');
        if (!$turnstileConfig instanceof TurnstileConfig) {
            $turnstileConfig = TurnstileConfig::fromEnv();
        }

        return Twig::fromRequest($request)->render($response, 'marketing/ticket_submit.twig', [
            'csrfToken' => $this->ensureCsrfToken(),
            'turnstileEnabled' => $turnstileConfig->isEnabled(),
            'turnstileSiteKey' => $turnstileConfig->isEnabled() ? $turnstileConfig->getSiteKey() : '',
            'error' => $error,
            'formData' => $data,
        ]);
    }

    private function ensureCsrfToken(): string
    {
        $token = $_SESSION['csrf_token'] ?? '';
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $_SESSION['csrf_token'] = $token;
        }

        return $token;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
