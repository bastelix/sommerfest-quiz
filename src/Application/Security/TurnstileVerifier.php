<?php

declare(strict_types=1);

namespace App\Application\Security;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TurnstileVerifier implements TurnstileVerifierInterface
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private string $secret;
    private ClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $endpoint;

    public function __construct(
        string $secret,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?string $endpoint = null
    ) {
        $this->secret = $secret;
        $this->httpClient = $httpClient ?? new Client(['timeout' => 5.0]);
        $this->logger = $logger ?? new NullLogger();
        $this->endpoint = $endpoint ?? self::ENDPOINT;
    }

    public function verify(string $token, ?string $ip = null): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $form = [
            'secret' => $this->secret,
            'response' => $token,
        ];
        if ($ip !== null && $ip !== '') {
            $form['remoteip'] = $ip;
        }

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'form_params' => $form,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $exception) {
            $this->logger->warning('Turnstile verification failed: ' . $exception->getMessage());

            return false;
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $this->logger->warning('Turnstile verification returned unexpected response body.');

            return false;
        }

        if (!empty($decoded['success'])) {
            return true;
        }

        $errors = [];
        if (isset($decoded['error-codes']) && is_array($decoded['error-codes'])) {
            foreach ($decoded['error-codes'] as $code) {
                if (is_scalar($code)) {
                    $errors[] = (string) $code;
                }
            }
        }

        if ($errors !== []) {
            $this->logger->info('Turnstile verification rejected token.', ['errors' => $errors]);
        }

        return false;
    }
}
