<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Verify Google ID tokens via the Google tokeninfo endpoint.
 */
class GoogleTokenVerifier
{
    private string $clientId;
    private ClientInterface $httpClient;

    public function __construct(string $clientId, ?ClientInterface $httpClient = null)
    {
        $this->clientId = $clientId;
        $this->httpClient = $httpClient ?? new Client(['timeout' => 5]);
    }

    /**
     * Verify a Google ID token and return the payload on success.
     *
     * @return array{sub: string, email: string, email_verified: string, name: string}|null
     */
    public function verify(string $idToken): ?array
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://oauth2.googleapis.com/tokeninfo',
                [
                    'query' => ['id_token' => $idToken],
                    'headers' => ['Accept' => 'application/json'],
                ]
            );
        } catch (Throwable $e) {
            error_log('Google token verification request failed: ' . $e->getMessage());
            return null;
        }

        $payload = (string) $response->getBody();
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            error_log('Google token verification returned unexpected payload.');
            return null;
        }

        if (($data['aud'] ?? '') !== $this->clientId) {
            error_log('Google token audience mismatch.');
            return null;
        }

        if (($data['email_verified'] ?? '') !== 'true') {
            error_log('Google token email not verified.');
            return null;
        }

        $email = (string) ($data['email'] ?? '');
        $sub = (string) ($data['sub'] ?? '');
        if ($email === '' || $sub === '') {
            return null;
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => (string) $data['email_verified'],
            'name' => (string) ($data['name'] ?? ''),
        ];
    }
}
