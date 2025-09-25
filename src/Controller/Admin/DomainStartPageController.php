<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\DomainStartPageService;
use App\Service\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API controller for configuring domain start pages.
 */
class DomainStartPageController
{
    private DomainStartPageService $domainService;
    private SettingsService $settingsService;

    public function __construct(DomainStartPageService $domainService, SettingsService $settingsService)
    {
        $this->domainService = $domainService;
        $this->settingsService = $settingsService;
    }

    public function index(Request $request, Response $response): Response
    {
        $mainDomain = getenv('MAIN_DOMAIN') ?: '';
        $marketing = getenv('MARKETING_DOMAINS') ?: '';
        $host = strtolower($request->getUri()->getHost());

        $domains = $this->domainService->determineDomains($mainDomain, (string) $marketing, $host);
        $mappings = $this->domainService->getAllMappings();
        $mainNormalized = $this->domainService->normalizeDomain((string) $mainDomain);
        $defaultMain = $this->settingsService->get('home_page', 'help');

        $items = [];
        foreach ($domains as $item) {
            $normalized = $item['normalized'];
            $type = $item['type'];
            $startPage = $mappings[$normalized] ?? null;
            if ($type === 'main' && ($startPage === null || $startPage === '')) {
                $startPage = $defaultMain;
            }
            if ($startPage === null || $startPage === '') {
                $startPage = 'landing';
            }
            $items[] = [
                'domain' => $item['domain'],
                'normalized' => $normalized,
                'type' => $type,
                'start_page' => $startPage,
            ];
        }

        $payload = [
            'domains' => $items,
            'options' => DomainStartPageService::START_PAGE_OPTIONS,
            'main' => $mainNormalized,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $domain = isset($data['domain']) ? (string) $data['domain'] : '';
        $startPage = isset($data['start_page']) ? (string) $data['start_page'] : '';
        if ($domain === '' || $startPage === '' || !in_array($startPage, DomainStartPageService::START_PAGE_OPTIONS, true)) {
            return $response->withStatus(400);
        }

        $mainDomain = getenv('MAIN_DOMAIN') ?: '';
        $marketing = getenv('MARKETING_DOMAINS') ?: '';
        $validDomains = $this->domainService->determineDomains($mainDomain, (string) $marketing);
        $normalized = $this->domainService->normalizeDomain($domain);

        $type = null;
        foreach ($validDomains as $item) {
            if ($item['normalized'] === $normalized) {
                $type = $item['type'];
                break;
            }
        }

        if ($type === null) {
            return $response->withStatus(404);
        }

        $this->domainService->saveStartPage($normalized, $startPage);
        if ($type === 'main') {
            $this->settingsService->save(['home_page' => $startPage]);
        }

        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
