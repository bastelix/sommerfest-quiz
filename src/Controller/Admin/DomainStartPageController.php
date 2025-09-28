<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\DomainStartPageService;
use App\Service\PageService;
use App\Service\SettingsService;
use App\Service\TranslationService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API controller for configuring domain start pages.
 */
class DomainStartPageController
{
    private DomainStartPageService $domainService;
    private SettingsService $settingsService;

    private PageService $pageService;

    public function __construct(
        DomainStartPageService $domainService,
        SettingsService $settingsService,
        PageService $pageService
    ) {
        $this->domainService = $domainService;
        $this->settingsService = $settingsService;
        $this->pageService = $pageService;
    }

    public function index(Request $request, Response $response): Response
    {
        $translator = $request->getAttribute('translator');
        $translationService = $translator instanceof TranslationService ? $translator : null;

        $mainDomain = getenv('MAIN_DOMAIN') ?: '';
        $marketing = getenv('MARKETING_DOMAINS') ?: '';
        $host = strtolower($request->getUri()->getHost());

        $domains = $this->domainService->determineDomains($mainDomain, (string) $marketing, $host);
        $mappings = $this->domainService->getAllMappings();
        $mainNormalized = $this->domainService->normalizeDomain((string) $mainDomain);
        $defaultMain = $this->settingsService->get('home_page', 'help');
        $options = $this->buildOptionLabels($translationService);

        $combined = [];
        foreach ($domains as $item) {
            $combined[$item['normalized']] = [
                'domain' => $item['domain'],
                'normalized' => $item['normalized'],
                'type' => $item['type'],
                'start_page' => null,
                'email' => null,
                'smtp_host' => null,
                'smtp_user' => null,
                'smtp_port' => null,
                'smtp_encryption' => null,
                'smtp_dsn' => null,
                'has_smtp_pass' => false,
            ];
        }

        foreach ($mappings as $domain => $config) {
            if (!isset($combined[$domain])) {
                $combined[$domain] = [
                    'domain' => $domain,
                    'normalized' => $domain,
                    'type' => 'custom',
                    'start_page' => $config['start_page'],
                    'email' => $config['email'],
                    'smtp_host' => $config['smtp_host'],
                    'smtp_user' => $config['smtp_user'],
                    'smtp_port' => $config['smtp_port'],
                    'smtp_encryption' => $config['smtp_encryption'],
                    'smtp_dsn' => $config['smtp_dsn'],
                    'has_smtp_pass' => $config['has_smtp_pass'],
                ];
                continue;
            }

            $combined[$domain]['start_page'] = $config['start_page'];
            $combined[$domain]['email'] = $config['email'];
            $combined[$domain]['smtp_host'] = $config['smtp_host'];
            $combined[$domain]['smtp_user'] = $config['smtp_user'];
            $combined[$domain]['smtp_port'] = $config['smtp_port'];
            $combined[$domain]['smtp_encryption'] = $config['smtp_encryption'];
            $combined[$domain]['smtp_dsn'] = $config['smtp_dsn'];
            $combined[$domain]['has_smtp_pass'] = $config['has_smtp_pass'];
        }

        $ordered = [];
        $normalizedOrder = array_map(static fn (array $item): string => $item['normalized'], $domains);
        foreach ($normalizedOrder as $key) {
            if (!isset($combined[$key])) {
                continue;
            }
            $ordered[] = $combined[$key];
            unset($combined[$key]);
        }

        if ($combined !== []) {
            ksort($combined);
            foreach ($combined as $item) {
                $ordered[] = $item;
            }
        }

        $items = [];
        foreach ($ordered as $item) {
            $startPage = $item['start_page'];
            if ($item['type'] === 'main' && ($startPage === null || $startPage === '')) {
                $startPage = $defaultMain;
            }
            if ($startPage === null || $startPage === '') {
                $startPage = 'landing';
            }

            $items[] = [
                'domain' => $item['domain'],
                'normalized' => $item['normalized'],
                'type' => $item['type'],
                'start_page' => $startPage,
                'email' => $item['email'],
                'smtp_host' => $item['smtp_host'],
                'smtp_user' => $item['smtp_user'],
                'smtp_port' => $item['smtp_port'],
                'smtp_encryption' => $item['smtp_encryption'],
                'smtp_dsn' => $item['smtp_dsn'],
                'has_smtp_pass' => $item['has_smtp_pass'],
            ];
        }

        $payload = [
            'domains' => $items,
            'options' => $options,
            'main' => $mainNormalized,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function save(Request $request, Response $response): Response
    {
        $translator = $request->getAttribute('translator');
        $translationService = $translator instanceof TranslationService ? $translator : null;

        $options = $this->buildOptionLabels($translationService);
        $validStartPages = array_keys($options);

        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $domain = isset($data['domain']) ? (string) $data['domain'] : '';
        $startPage = isset($data['start_page']) ? (string) $data['start_page'] : '';
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $smtpHost = array_key_exists('smtp_host', $data) ? trim((string) $data['smtp_host']) : null;
        $smtpUser = array_key_exists('smtp_user', $data) ? trim((string) $data['smtp_user']) : null;
        $smtpPort = $data['smtp_port'] ?? null;
        if (is_string($smtpPort)) {
            $smtpPort = trim($smtpPort);
            if ($smtpPort === '') {
                $smtpPort = null;
            }
        }
        $smtpEncryption = array_key_exists('smtp_encryption', $data)
            ? trim((string) $data['smtp_encryption'])
            : null;
        $smtpDsn = array_key_exists('smtp_dsn', $data) ? trim((string) $data['smtp_dsn']) : null;
        $smtpPass = array_key_exists('smtp_pass', $data)
            ? (string) $data['smtp_pass']
            : DomainStartPageService::SECRET_PLACEHOLDER;
        if ($domain === '' || $startPage === '' || !in_array($startPage, $validStartPages, true)) {
            return $response->withStatus(400);
        }

        $emailValue = $email === '' ? null : $email;
        if ($emailValue !== null && filter_var($emailValue, FILTER_VALIDATE_EMAIL) === false) {
            $message = $translationService?->translate('notify_domain_start_page_invalid_email')
                ?? 'Please provide a valid email address or leave the field empty.';
            $response->getBody()->write(json_encode(['error' => $message]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        }

        $smtpConfig = [
            'smtp_host' => $smtpHost,
            'smtp_user' => $smtpUser,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'smtp_dsn' => $smtpDsn,
            'smtp_pass' => $smtpPass,
        ];

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

        try {
            $this->domainService->saveDomainConfig($normalized, $startPage, $emailValue, $smtpConfig);
        } catch (InvalidArgumentException $e) {
            $message = $translationService?->translate('notify_domain_start_page_invalid_smtp')
                ?? $e->getMessage();
            $response->getBody()->write(json_encode(['error' => $message]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        }
        if ($type === 'main') {
            $this->settingsService->save(['home_page' => $startPage]);
        }

        $config = $this->domainService->getDomainConfig($normalized);
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'config' => $config,
            'options' => $options,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @return array<string,string>
     */
    private function buildOptionLabels(?TranslationService $translationService): array
    {
        $options = $this->domainService->getStartPageOptions($this->pageService);

        if ($translationService !== null) {
            $options['help'] = $translationService->translate('option_help_page');
            $options['events'] = $translationService->translate('option_events_page');
        }

        $coreOrder = ['help', 'events'];
        $ordered = [];
        foreach ($coreOrder as $slug) {
            if (isset($options[$slug])) {
                $ordered[$slug] = $options[$slug];
                unset($options[$slug]);
            }
        }

        return $ordered + $options;
    }
}
