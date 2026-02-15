<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\MailProvider\MailProviderManager;
use App\Service\NamespaceResolver;
use App\Service\NewsletterSubscriptionService;
use App\Infrastructure\Database;
use App\Service\TurnstileConfig;
use App\Service\TurnstileVerificationService;
use App\Service\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Handles newsletter subscription from CMS CTA block instances.
 *
 * Returns JSON responses for AJAX consumption.
 */
class BlockNewsletterController
{
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
            $key = 'block_newsletter_honeypot:' . $ip;
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
                error_log(sprintf('Block newsletter honeypot triggered (ip=%s, ua=%s)', $ip, $ua));
            }

            return $this->json($response, ['status' => 'ok']);
        }

        // Required field
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, ['error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'], 400);
        }

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

        // Resolve namespace and build mail provider
        $pdo = Database::connectFromEnv();
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $manager = $request->getAttribute('mailProviderManager');
        if (!$manager instanceof MailProviderManager) {
            $manager = new MailProviderManager(new SettingsService($pdo), [], null, $namespace);
        }

        if (!$manager->isConfigured()) {
            return $this->json($response, ['error' => 'Newsletter-Service nicht konfiguriert'], 503);
        }

        $newsletterService = new NewsletterSubscriptionService($manager, $namespace);

        $serverParams = $request->getServerParams();
        $metadata = [
            'ip' => isset($serverParams['REMOTE_ADDR']) ? (string) $serverParams['REMOTE_ADDR'] : null,
            'user_agent' => isset($serverParams['HTTP_USER_AGENT']) ? (string) $serverParams['HTTP_USER_AGENT'] : null,
            'referer' => $request->getHeaderLine('Referer') ?: null,
            'landing' => strtolower($request->getUri()->getHost()),
        ];

        $source = trim((string) ($data['source'] ?? ''));
        $attributes = [
            'SOURCE' => $source !== '' ? $source : 'cta-newsletter',
        ];

        try {
            $newsletterService->subscribe($email, $metadata, $attributes);
        } catch (\Throwable $e) {
            error_log('Block newsletter subscription failed: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Anmeldung fehlgeschlagen. Bitte versuchen Sie es später erneut.'], 500);
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
