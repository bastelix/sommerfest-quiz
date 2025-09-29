<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InvitationService;
use App\Service\MailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Handle sending invitation mails.
 */
class InvitationController
{
    private InvitationService $service;
    private MailService $mailer;

    public function __construct(InvitationService $service, MailService $mailer) {
        $this->service = $service;
        $this->mailer = $mailer;
    }

    /**
     * Accept email and name, send invitation link.
     */
    public function send(Request $request, Response $response): Response {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $email = trim((string) ($data['email'] ?? ''));
        $name  = trim((string) ($data['name'] ?? ''));
        if ($email === '' || $name === '') {
            return $response->withStatus(400);
        }

        $token = $this->service->createToken($email);
        $uri = $request->getUri()
            ->withPath('/register')
            ->withQuery(http_build_query(['token' => $token]));

        $this->mailer->sendInvitation($email, $name, (string) $uri);

        return $response->withStatus(204);
    }
}
