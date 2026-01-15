<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\AuditLogger;
use App\Service\EventService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\PlayerContactOptInService;
use App\Service\SettingsService;
use App\Service\NamespaceResolver;
use InvalidArgumentException;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Throwable;

/**
 * REST endpoints for managing player contact double opt-in.
 */
class PlayerContactController
{
    private PlayerContactOptInService $optIns;

    private EventService $events;

    public function __construct(PlayerContactOptInService $optIns, EventService $events)
    {
        $this->optIns = $optIns;
        $this->events = $events;
    }

    public function request(Request $request, Response $response): Response
    {
        $data = $this->parseJson($request);
        $eventUid = (string) ($data['event_uid'] ?? '');
        $playerUid = (string) ($data['player_uid'] ?? '');
        $playerName = (string) ($data['player_name'] ?? '');
        $email = (string) ($data['contact_email'] ?? '');

        if ($eventUid === '' || $playerUid === '' || $email === '') {
            return $response->withStatus(400);
        }

        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $event = $this->events->getByUid($eventUid, $namespace);
        if ($event === null) {
            return $response->withStatus(404);
        }

        try {
            $optIn = $this->optIns->createRequest(
                $eventUid,
                $playerUid,
                $playerName,
                $email,
                $this->clientIp($request)
            );
        } catch (InvalidArgumentException $exception) {
            return $response->withStatus(400);
        }

        $providerManager = $this->resolveProviderManager($request);
        if (!$providerManager->isConfigured()) {
            return $response->withStatus(503);
        }

        $mailer = $this->resolveMailer($request, $providerManager);
        if ($mailer === null) {
            return $response->withStatus(503);
        }

        $link = $this->buildConfirmationLink($request, $optIn['event_uid'], $optIn['player_uid'], $optIn['token']);

        try {
            $mailer->sendDoubleOptIn($optIn['email'], $link, [
                'subject' => 'Bitte bestätige deine E-Mail-Adresse',
                'player_name' => $optIn['player_name'],
                'event_name' => $event['name'],
                'headline' => 'Bitte bestätige deine E-Mail-Adresse',
                'action_label' => 'E-Mail bestätigen',
            ]);
        } catch (Throwable $exception) {
            return $response->withStatus(500);
        }

        return $response->withStatus(204);
    }

    public function confirm(Request $request, Response $response): Response
    {
        $data = $this->parseJson($request);
        $token = (string) ($data['token'] ?? $data['contact_token'] ?? '');
        if ($token === '') {
            return $response->withStatus(400);
        }

        $result = $this->optIns->confirm($token, $this->clientIp($request));
        $status = $result['status'];

        if ($status !== 'success') {
            $code = match ($status) {
                'expired', 'consumed' => 410,
                'not_found' => 404,
                'conflict' => 409,
                default => 400,
            };

            return $response->withStatus($code);
        }

        $payload = [
            'event_uid' => $result['event_uid'] ?? '',
            'player_uid' => $result['player_uid'] ?? '',
            'player_name' => $result['player_name'] ?? '',
            'contact_email' => $result['email'] ?? '',
        ];

        $response->getBody()->write((string) json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response): Response
    {
        $data = $this->parseJson($request);
        $eventUid = (string) ($data['event_uid'] ?? '');
        $playerUid = (string) ($data['player_uid'] ?? '');

        if ($eventUid === '' || $playerUid === '') {
            return $response->withStatus(400);
        }

        $removed = $this->optIns->remove($eventUid, $playerUid);
        if (!$removed) {
            return $response->withStatus(404);
        }

        return $response->withStatus(204);
    }

    /**
     * @return array<string,mixed>
     */
    private function parseJson(Request $request): array
    {
        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function clientIp(Request $request): ?string
    {
        $server = $request->getServerParams();
        $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? $server['http_x_forwarded_for'] ?? null;
        if (is_string($forwarded) && $forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            return $parts[0] !== '' ? $parts[0] : null;
        }

        $remote = $server['REMOTE_ADDR'] ?? $server['remote_addr'] ?? null;
        return is_string($remote) && $remote !== '' ? $remote : null;
    }

    private function buildConfirmationLink(Request $request, string $eventUid, string $playerUid, string $token): string
    {
        $routeContext = RouteContext::fromRequest($request);
        $basePath = rtrim($routeContext->getBasePath(), '/');
        $uri = $request->getUri()
            ->withPath($basePath . '/ranking')
            ->withQuery(http_build_query([
                'event' => $eventUid,
                'uid' => $playerUid,
                'contact_token' => $token,
            ]));

        return (string) $uri;
    }

    private function resolveProviderManager(Request $request): MailProviderManager
    {
        $providerManager = $request->getAttribute('mailProviderManager');
        if ($providerManager instanceof MailProviderManager) {
            return $providerManager;
        }

        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        return new MailProviderManager(new SettingsService($pdo), [], null, $namespace);
    }

    private function resolveMailer(Request $request, MailProviderManager $providerManager): ?MailService
    {
        $mailer = $request->getAttribute('mailService');
        if ($mailer instanceof MailService) {
            return $mailer;
        }

        if (!$providerManager->isConfigured()) {
            return null;
        }

        $twig = Twig::fromRequest($request)->getEnvironment();
        $audit = $request->getAttribute('auditLogger');

        return new MailService(
            $twig,
            $providerManager,
            $audit instanceof AuditLogger ? $audit : null
        );
    }
}
