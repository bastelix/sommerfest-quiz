<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\MailService;
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
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            $data = $request->getParsedBody();
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        if ($name === '' || $email === '' || $message === '') {
            return $response->withStatus(400);
        }

        $profileFile = dirname(__DIR__, 3) . '/data/profile.json';
        $profile = [];
        if (is_readable($profileFile)) {
            $profile = json_decode((string) file_get_contents($profileFile), true) ?: [];
        }
        $to = (string) ($profile['imprint_email'] ?? '');
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
