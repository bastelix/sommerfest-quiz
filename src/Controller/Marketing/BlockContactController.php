<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\DomainContactTemplateService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\NamespaceResolver;
use App\Infrastructure\Database;
use App\Service\TenantService;
use App\Service\TurnstileConfig;
use App\Service\TurnstileVerificationService;
use App\Service\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use RuntimeException;

/**
 * Handles contact form submissions from CMS block instances.
 *
 * Returns JSON responses for AJAX consumption.
 */
class BlockContactController
{
    private const MAX_MESSAGE_LENGTH = 5000;

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $data = json_decode((string) $body, true);
        }
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'Invalid request'], 400);
        }

        // Honeypot – silently accept to hide detection from bots
        $honeypot = trim((string) ($data['company'] ?? ''));
        if ($honeypot !== '') {
            $serverParams = $request->getServerParams();
            $ip = (string) ($serverParams['REMOTE_ADDR'] ?? 'unknown');
            $key = 'block_contact_honeypot:' . $ip;
            $shouldLog = true;

            if (
                function_exists('apcu_fetch') &&
                function_exists('apcu_store') &&
                function_exists('apcu_exists') &&
                (!function_exists('apcu_enabled') || apcu_enabled())
            ) {
                $count = 0;
                if (apcu_exists($key)) {
                    $count = (int) apcu_fetch($key);
                }
                $shouldLog = $count < 5;
                apcu_store($key, $count + 1, 300);
            }

            if ($shouldLog) {
                $ua = (string) ($serverParams['HTTP_USER_AGENT'] ?? 'unknown');
                error_log(sprintf('Block contact honeypot triggered (ip=%s, ua=%s)', $ip, $ua));
            }

            return $this->json($response, ['status' => 'ok']);
        }

        // Required fields
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, ['error' => 'Name und gültige E-Mail-Adresse erforderlich'], 400);
        }

        $messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
        if ($messageLength > self::MAX_MESSAGE_LENGTH) {
            return $this->json($response, ['error' => 'Nachricht zu lang'], 400);
        }

        // Optional extra fields
        $subject = trim((string) ($data['subject'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $companyName = trim((string) ($data['company_name'] ?? ''));

        // Turnstile CAPTCHA
        $turnstileConfig = $request->getAttribute('turnstileConfig');
        if (!$turnstileConfig instanceof TurnstileConfig) {
            $turnstileConfig = TurnstileConfig::fromEnv();
        }
        $turnstileVerifier = $request->getAttribute('turnstileVerifier');
        if (!$turnstileVerifier instanceof TurnstileVerificationService) {
            $turnstileVerifier = new TurnstileVerificationService($turnstileConfig);
        }
        if ($turnstileConfig->isEnabled()) {
            $token = (string) ($data['cf-turnstile-response'] ?? '');
            $remoteIp = $request->getServerParams()['REMOTE_ADDR'] ?? null;
            $ip = is_string($remoteIp) ? $remoteIp : null;
            if ($token === '' || !$turnstileVerifier->verify($token, $ip)) {
                return $this->json($response, ['error' => 'CAPTCHA-Prüfung fehlgeschlagen'], 422);
            }
        }

        // Resolve recipient from form data, fall back to tenant imprint_email
        $pdo = Database::connectFromEnv();
        $tenant = (new TenantService($pdo))->getMainTenant();
        $tenantEmail = (string) ($tenant['imprint_email'] ?? '');
        $formRecipient = trim((string) ($data['recipient'] ?? ''));
        $to = ($formRecipient !== '' && filter_var($formRecipient, FILTER_VALIDATE_EMAIL))
            ? $formRecipient
            : $tenantEmail;

        if ($to === '') {
            return $this->json($response, ['error' => 'Empfänger nicht konfiguriert'], 500);
        }

        // Domain-specific template
        $templateService = new DomainContactTemplateService($pdo);
        $host = strtolower($request->getUri()->getHost());
        $template = $templateService->getForHost($host);

        // Build mail service via provider manager
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $manager = $request->getAttribute('mailProviderManager');
        if (!$manager instanceof MailProviderManager) {
            $manager = new MailProviderManager(new SettingsService($pdo), [], null, $namespace);
        }

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!$manager->isConfigured()) {
                return $this->json($response, ['error' => 'Mailservice nicht konfiguriert'], 503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $manager);
        }

        // Collect extra fields for template rendering
        $extraFields = [];
        if ($subject !== '') {
            $extraFields[] = ['label' => 'Betreff', 'value' => $subject];
        }
        if ($phone !== '') {
            $extraFields[] = ['label' => 'Telefon', 'value' => $phone];
        }
        if ($companyName !== '') {
            $extraFields[] = ['label' => 'Unternehmen', 'value' => $companyName];
        }

        try {
            $mailer->sendContact(
                $to,
                $name,
                $email,
                $message,
                $template,
                null,
                null,
                $companyName ?: null,
                $subject ?: null,
                $extraFields ?: null
            );
        } catch (RuntimeException $e) {
            error_log('Block contact mail failed: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Mailversand fehlgeschlagen'], 500);
        }

        return $this->json($response, ['status' => 'ok']);
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
