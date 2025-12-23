<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\DomainContactTemplateService;
use App\Service\DomainService;
use App\Service\TranslationService;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Admin API for managing domain specific contact templates.
 */
class DomainContactTemplateController
{
    private DomainContactTemplateService $templates;
    private DomainService $domains;

    public function __construct(DomainContactTemplateService $templates, DomainService $domains) {
        $this->templates = $templates;
        $this->domains = $domains;
    }

    public function show(Request $request, Response $response, array $args): Response {
        $domain = isset($args['domain']) ? (string) $args['domain'] : '';
        $normalized = $this->domains->normalizeDomain($domain);
        if ($normalized === '' || !$this->isAllowedDomain($normalized)) {
            return $response->withStatus(404);
        }

        $template = $this->templates->get($normalized) ?? [
            'domain' => $normalized,
            'sender_name' => null,
            'recipient_html' => null,
            'recipient_text' => null,
            'sender_html' => null,
            'sender_text' => null,
        ];

        $payload = [
            'domain' => $normalized,
            'sender_name' => $template['sender_name'] ?? '',
            'recipient_html' => $template['recipient_html'] ?? '',
            'recipient_text' => $template['recipient_text'] ?? '',
            'sender_html' => $template['sender_html'] ?? '',
            'sender_text' => $template['sender_text'] ?? '',
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function save(Request $request, Response $response): Response {
        $translator = $request->getAttribute('translator');
        $translationService = $translator instanceof TranslationService ? $translator : null;

        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $domain = isset($data['domain']) ? (string) $data['domain'] : '';
        $normalized = $this->domains->normalizeDomain($domain);
        if ($normalized === '' || !$this->isAllowedDomain($normalized)) {
            $message = $translationService?->translate('notify_domain_contact_template_invalid_domain')
                ?? 'Domain is not allowed.';
            $response->getBody()->write(json_encode(['error' => $message]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        }

        $payload = [
            'sender_name' => isset($data['sender_name']) ? (string) $data['sender_name'] : null,
            'recipient_html' => isset($data['recipient_html']) ? (string) $data['recipient_html'] : null,
            'recipient_text' => isset($data['recipient_text']) ? (string) $data['recipient_text'] : null,
            'sender_html' => isset($data['sender_html']) ? (string) $data['sender_html'] : null,
            'sender_text' => isset($data['sender_text']) ? (string) $data['sender_text'] : null,
        ];

        try {
            $this->templates->save($normalized, $payload);
        } catch (PDOException $e) {
            $message = $translationService?->translate('notify_domain_contact_template_error')
                ?? 'Saving template failed.';
            $response->getBody()->write(json_encode(['error' => $message]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $template = $this->templates->get($normalized);

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'template' => [
                'domain' => $normalized,
                'sender_name' => $template['sender_name'] ?? '',
                'recipient_html' => $template['recipient_html'] ?? '',
                'recipient_text' => $template['recipient_text'] ?? '',
                'sender_html' => $template['sender_html'] ?? '',
                'sender_text' => $template['sender_text'] ?? '',
            ],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function isAllowedDomain(string $normalized): bool {
        $mainDomain = $this->domains->normalizeDomain((string) (getenv('MAIN_DOMAIN') ?: ''));
        if ($mainDomain !== '' && $mainDomain === $normalized) {
            return true;
        }

        foreach ($this->domains->listDomains(includeInactive: true) as $entry) {
            if ($entry['normalized_host'] === $normalized) {
                return true;
            }
        }

        return false;
    }
}
