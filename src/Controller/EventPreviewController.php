<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\EventService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EventPreviewController
{
    private ConfigService $config;
    private EventService $events;

    public function __construct(ConfigService $config, EventService $events)
    {
        $this->config = $config;
        $this->events = $events;
    }

    /**
     * Unlock a restricted event for the current session when the preview password matches.
     */
    public function unlock(Request $request, Response $response, array $args): Response
    {
        $uid = (string) ($args['uid'] ?? '');
        if ($uid === '') {
            return $response->withStatus(404);
        }

        $event = $this->events->getByUid($uid);
        if ($event === null) {
            return $response->withStatus(404);
        }

        $hash = $this->config->getPreviewPasswordHash($uid);
        $slug = (string) ($event['slug'] ?? $uid);

        if ($hash === null) {
            $_SESSION['event_preview_error'] = 'missing';
            return $this->redirectToEvent($response, $slug);
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        $password = (string) ($data['preview_password'] ?? $data['previewPassword'] ?? '');
        $password = trim($password);

        if ($password === '' || !password_verify($password, $hash)) {
            $_SESSION['event_preview_error'] = 'invalid';
            return $this->redirectToEvent($response, $slug);
        }

        unset($_SESSION['event_preview_error']);
        if (!isset($_SESSION['event_preview']) || !is_array($_SESSION['event_preview'])) {
            $_SESSION['event_preview'] = [];
        }
        $_SESSION['event_preview'][$uid] = true;

        return $this->redirectToEvent($response, $slug);
    }

    private function redirectToEvent(Response $response, string $slug): Response
    {
        $location = '/?event=' . rawurlencode($slug);
        return $response->withHeader('Location', $location)->withStatus(302);
    }
}
