<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SettingsService;
use App\Domain\Roles;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API endpoints for application settings.
 */
class SettingsController
{
    private const SECRET_PLACEHOLDER = '__SECRET_PRESENT__';

    private SettingsService $service;

    public function __construct(SettingsService $service) {
        $this->service = $service;
    }

    public function get(Request $request, Response $response): Response {
        $settings = $this->maskSecrets($this->service->getAll());
        $response->getBody()->write(json_encode($settings, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function post(Request $request, Response $response): Response {
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== Roles::ADMIN) {
            return $response->withStatus(403);
        }
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string)$request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $filtered = $this->filterPayload($data);
        if ($filtered !== []) {
            $this->service->save($filtered);
        }
        return $response->withStatus(204);
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    private function maskSecrets(array $settings): array
    {
        $masked = $settings;
        $token = isset($masked['rag_chat_service_token'])
            ? trim((string) $masked['rag_chat_service_token'])
            : '';
        $masked['rag_chat_service_token_present'] = $token !== '' ? '1' : '0';
        if ($token !== '') {
            $masked['rag_chat_service_token'] = self::SECRET_PLACEHOLDER;
        }

        return $masked;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private function filterPayload(array $payload): array
    {
        $filtered = [];

        foreach ($payload as $key => $value) {
            if ($key === '') {
                continue;
            }

            if ($key === 'rag_chat_service_token') {
                if ($value === self::SECRET_PLACEHOLDER) {
                    continue;
                }

                $filtered[$key] = $this->normaliseValue($value);
                continue;
            }

            if ($key === 'rag_chat_service_force_openai') {
                $filtered[$key] = $this->isTruthy($value) ? '1' : '0';
                continue;
            }

            $filtered[$key] = $this->normaliseValue($value);
        }

        return $filtered;
    }

    /**
     * @param mixed $value
     */
    private function normaliseValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (!is_string($value)) {
            return false;
        }

        $normalized = strtolower(trim($value));

        return $normalized !== '' && in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
