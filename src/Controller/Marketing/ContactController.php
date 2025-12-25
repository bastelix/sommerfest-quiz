<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\DomainContactTemplateService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\NamespaceResolver;
use App\Service\NewsletterSubscriptionService;
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
 * Handles contact form submissions from the landing page.
 */
class ContactController
{
    public function __invoke(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $data = json_decode((string) $body, true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $honeypot = trim((string) ($data['company'] ?? ''));
        if ($honeypot !== '') {
            $serverParams = $request->getServerParams();
            $ip = (string) ($serverParams['REMOTE_ADDR'] ?? 'unknown');
            $key = 'contact_honeypot:' . $ip;
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
                error_log(sprintf('Contact honeypot triggered (ip=%s, ua=%s)', $ip, $ua));
            }

            return $response->withStatus(204);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $companyName = trim((string) ($data['company_name'] ?? ''));
        $messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
        if (
            $name === '' ||
            $message === '' ||
            $email === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $messageLength > 160
        ) {
            return $response->withStatus(400);
        }

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
                return $response->withStatus(422);
            }
        }

        $pdo = Database::connectFromEnv();
        $templateService = new DomainContactTemplateService($pdo);
        $host = strtolower($request->getUri()->getHost());
        $template = $templateService->getForHost($host);
        $tenant = (new TenantService($pdo))->getMainTenant();
        $to = (string) ($tenant['imprint_email'] ?? '');
        if ($to === '') {
            return $response->withStatus(500);
        }

        $manager = $request->getAttribute('mailProviderManager');
        if (!$manager instanceof MailProviderManager) {
            $pdo = Database::connectFromEnv();
            $manager = new MailProviderManager(new SettingsService($pdo));
        }

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!$manager->isConfigured()) {
                $response->getBody()->write('Mailservice nicht konfiguriert');
                return $response->withStatus(503)->withHeader('Content-Type', 'text/plain');
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $manager);
        }
        try {
            $mailer->sendContact($to, $name, $email, $message, $template, null, null, $companyName ?: null);
        } catch (RuntimeException $e) {
            error_log('Contact mail failed: ' . $e->getMessage());
            $response->getBody()->write('Mailversand fehlgeschlagen');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }

        $newsletterAction = strtolower(trim((string) ($data['newsletter_action'] ?? '')));
        $subscribeField = $data['newsletter_subscribe'] ?? null;
        $shouldSubscribe = $newsletterAction === 'subscribe';
        if (!$shouldSubscribe && $newsletterAction === '') {
            if (is_string($subscribeField)) {
                $normalized = strtolower(trim($subscribeField));
                $shouldSubscribe = in_array($normalized, ['1', 'true', 'yes', 'on'], true);
            } elseif (is_bool($subscribeField)) {
                $shouldSubscribe = $subscribeField;
            }
        }
        $shouldUnsubscribe = $newsletterAction === 'unsubscribe';

        if ($shouldSubscribe || $shouldUnsubscribe) {
            $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
            $newsletterService = new NewsletterSubscriptionService(
                $manager,
                $namespace,
            );

            $serverParams = $request->getServerParams();
            $metadata = [
                'ip' => isset($serverParams['REMOTE_ADDR']) ? (string) $serverParams['REMOTE_ADDR'] : null,
                'user_agent' => isset($serverParams['HTTP_USER_AGENT']) ? (string) $serverParams['HTTP_USER_AGENT'] : null,
                'referer' => $request->getHeaderLine('Referer') ?: null,
                'landing' => $host,
            ];

            try {
                if ($shouldSubscribe) {
                    $attributes = ['FIRSTNAME' => $name];
                    $attributes['SOURCE'] = 'marketing-contact';

                    $newsletterService->subscribe($email, $metadata, $attributes);
                } else {
                    $newsletterService->unsubscribe($email, $metadata);
                }
            } catch (RuntimeException $exception) {
                error_log('Newsletter processing failed: ' . $exception->getMessage());
                $response->getBody()->write('Newsletter-Verarbeitung fehlgeschlagen');
                return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
            }
        }

        return $response->withStatus(204);
    }
}
