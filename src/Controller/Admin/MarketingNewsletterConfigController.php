<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\MarketingNewsletterConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class MarketingNewsletterConfigController
{
    private MarketingNewsletterConfigService $configService;

    public function __construct(MarketingNewsletterConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function index(Request $request, Response $response): Response
    {
        $payload = [
            'items' => $this->configService->getAllGrouped(),
            'styles' => $this->configService->getAllowedStyles(),
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $slug = isset($data['slug']) ? (string) $data['slug'] : '';
        $entries = $data['entries'] ?? [];
        if (!is_array($entries)) {
            return $response->withStatus(400);
        }

        $normalizedEntries = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalizedEntries[] = [
                'label' => $entry['label'] ?? '',
                'url' => $entry['url'] ?? '',
                'style' => $entry['style'] ?? null,
            ];
        }

        try {
            $this->configService->saveEntries($slug, $normalizedEntries);
        } catch (RuntimeException $exception) {
            return $response->withStatus(400);
        }

        return $response->withStatus(204);
    }
}
