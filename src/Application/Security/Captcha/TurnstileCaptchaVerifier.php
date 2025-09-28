<?php

declare(strict_types=1);

namespace App\Application\Security\Captcha;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

final class TurnstileCaptchaVerifier implements CaptchaVerifierInterface
{
    private string $secret;
    private ClientInterface $httpClient;

    public function __construct(string $secret, ?ClientInterface $httpClient = null)
    {
        $secret = trim($secret);
        if ($secret === '') {
            throw new \InvalidArgumentException('Turnstile secret must not be empty.');
        }

        $this->secret = $secret;
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 5.0,
        ]);
    }

    public function verify(string $token, ?string $ipAddress = null): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'form_params' => array_filter([
                    'secret' => $this->secret,
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ], static fn ($value): bool => $value !== null && $value !== ''),
                'headers' => [
                    'User-Agent' => 'QuizRace/ContactCaptchaVerifier',
                ],
            ]);
        } catch (GuzzleException $exception) {
            error_log('Turnstile verification failed: ' . $exception->getMessage());
            return false;
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            return false;
        }

        return (bool) ($payload['success'] ?? false);
    }
}
