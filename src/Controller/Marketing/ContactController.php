<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

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
        $tenant = (new TenantService($pdo))->getMainTenant();
        $to = (string) ($tenant['imprint_email'] ?? '');
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
            $mailer->sendContact($to, $name, $email, $message);
        } catch (RuntimeException $e) {
            error_log('Contact mail failed: ' . $e->getMessage());
            $response->getBody()->write('Mailversand fehlgeschlagen');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }

        return $response->withStatus(204);
    }
}
