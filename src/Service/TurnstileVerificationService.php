<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class TurnstileVerificationService
{
    private ClientInterface $httpClient;
    private TurnstileConfig $config;
    private LoggerInterface $logger;

    public function __construct(
        TurnstileConfig $config,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 5,
        ]);
        $this->logger = $logger ?? new NullLogger();
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (!$this->config->isEnabled()) {
            return true;
        }

        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            return false;
        }

        $secret = $this->config->getSecretKey();
        if ($secret === null) {
            return false;
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'form_params' => array_filter(
                        [
                            'secret' => $secret,
                            'response' => $token,
                            'remoteip' => $ip !== null ? trim($ip) : null,
                        ],
                        static fn ($value): bool => $value !== null && $value !== ''
                    ),
                ]
            );
        } catch (Throwable $e) {
            $this->logger->warning('Turnstile verification request failed: ' . $e->getMessage());

            return false;
        }

        $payload = (string) $response->getBody();
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            $this->logger->warning('Turnstile verification returned unexpected payload.', ['body' => $payload]);

            return false;
        }

        return (bool) ($data['success'] ?? false);
    }
}
