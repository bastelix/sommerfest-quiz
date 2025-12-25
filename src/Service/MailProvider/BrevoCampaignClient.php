<?php

declare(strict_types=1);

namespace App\Service\MailProvider;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Minimal Brevo client for creating and sending email campaigns.
 */
class BrevoCampaignClient
{
    private ClientInterface $client;

    private string $apiKey;

    public function __construct(string $apiKey, ?ClientInterface $client = null)
    {
        $this->apiKey = trim($apiKey);
        if ($this->apiKey === '') {
            throw new RuntimeException('Brevo API key is required for campaign delivery.');
        }

        $this->client = $client ?? new Client(['base_uri' => 'https://api.brevo.com/v3/']);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,string|null>
     */
    public function createAndSend(string $name, int $templateId, int $listId, array $params = []): array
    {
        $payload = [
            'name' => $name,
            'templateId' => $templateId,
            'recipients' => [
                'listIds' => [$listId],
            ],
            'type' => 'classic',
            'params' => $params,
        ];

        $response = $this->request('POST', 'emailCampaigns', $payload);
        $data = $this->decodeJson($response);
        $campaignId = isset($data['id']) ? (int) $data['id'] : null;
        if ($campaignId === null || $campaignId <= 0) {
            throw new RuntimeException('Brevo did not return a campaign identifier.');
        }

        $sendResponse = $this->request('POST', sprintf('emailCampaigns/%d/send', $campaignId));
        $sendData = $this->decodeJson($sendResponse);

        return [
            'campaign_id' => (string) $campaignId,
            'message_id' => isset($sendData['messageId']) ? (string) $sendData['messageId'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function request(string $method, string $uri, array $payload = []): string
    {
        try {
            $options = [
                'headers' => [
                    'api-key' => $this->apiKey,
                    'accept' => 'application/json',
                ],
                'timeout' => 5.0,
            ];
            if ($payload !== []) {
                $options['json'] = $payload;
            }

            $response = $this->client->request($method, $uri, $options);

            return (string) $response->getBody();
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Failed to communicate with Brevo API: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param string $content
     * @return array<string,mixed>
     */
    private function decodeJson(string $content): array
    {
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected response from Brevo API.');
        }

        return $decoded;
    }
}
