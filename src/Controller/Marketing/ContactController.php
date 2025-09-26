<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\DomainContactTemplateService;
use App\Service\DomainStartPageService;
use App\Service\MailService;
use App\Infrastructure\Database;
use App\Service\TenantService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use RuntimeException;

/**
 * Handles contact form submissions from the landing page.
 */
class ContactController
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
        if (
            $name === '' ||
            $message === '' ||
            $email === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            return $response->withStatus(400);
        }

        $pdo = Database::connectFromEnv();
        $domainService = new DomainStartPageService($pdo);
        $templateService = new DomainContactTemplateService($pdo, $domainService);
        $host = strtolower($request->getUri()->getHost());
        $domainConfig = $domainService->getDomainConfig($host);
        $domainEmail = null;
        if ($domainConfig !== null && array_key_exists('email', $domainConfig) && $domainConfig['email'] !== null) {
            $domainEmail = trim((string) $domainConfig['email']);
            if ($domainEmail === '') {
                $domainEmail = null;
            }
        }
        $template = $templateService->getForHost($host);
        $tenant = (new TenantService($pdo))->getMainTenant();
        $to = $domainEmail ?? (string) ($tenant['imprint_email'] ?? '');
        if ($to === '') {
            return $response->withStatus(500);
        }

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!MailService::isConfigured()) {
                $response->getBody()->write('Mailservice nicht konfiguriert');
                return $response->withStatus(503)->withHeader('Content-Type', 'text/plain');
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig);
        }
        try {
            $mailer->sendContact($to, $name, $email, $message, $template, $domainEmail);
        } catch (RuntimeException $e) {
            error_log('Contact mail failed: ' . $e->getMessage());
            $response->getBody()->write('Mailversand fehlgeschlagen');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }

        return $response->withStatus(204);
    }
}
