<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\CertificateProvisioningService;
use App\Service\DomainStartPageService;
use App\Service\MarketingDomainProvider;
use App\Service\PageService;
use App\Service\SettingsService;
use App\Service\TranslationService;
use App\Support\DomainNameHelper;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API controller for configuring domain start pages.
 */
class DomainStartPageController
{
    private DomainStartPageService $domainService;
    private CertificateProvisioningService $certificateProvisioner;
    private SettingsService $settingsService;

    private PageService $pageService;

    private MarketingDomainProvider $marketingDomainProvider;

    public function __construct(
        DomainStartPageService $domainService,
        CertificateProvisioningService $certificateProvisioner,
        SettingsService $settingsService,
        PageService $pageService,
        MarketingDomainProvider $marketingDomainProvider
    ) {
        $this->domainService = $domainService;
        $this->certificateProvisioner = $certificateProvisioner;
        $this->settingsService = $settingsService;
        $this->pageService = $pageService;
        $this->marketingDomainProvider = $marketingDomainProvider;
    }

    public function index(Request $request, Response $response): Response {
        $translator = $request->getAttribute('translator');
        $translationService = $translator instanceof TranslationService ? $translator : null;

        $overview = $this->buildDomainOverview(strtolower($request->getUri()->getHost()));
        $overview['options'] = $this->buildOptionLabels($translationService);

        $response->getBody()->write(json_encode($overview, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function save(Request $request, Response $response): Response {
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
        $overview = $this->buildDomainOverview(strtolower($request->getUri()->getHost()));
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'config' => $config,
            'options' => $options,
            'domains' => $overview['domains'],
            'marketing_domains' => $overview['marketing_domains'],
            'main' => $overview['main'],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createMarketingDomain(Request $request, Response $response): Response {
        $translator = $request->getAttribute('translator');
        $translationService = $translator instanceof TranslationService ? $translator : null;

        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $host = isset($data['domain']) ? (string) $data['domain'] : '';
        if ($host === '' && isset($data['host'])) {
            $host = (string) $data['host'];
        }
        $label = array_key_exists('label', $data) ? $data['label'] : null;
        $label = $label === null ? null : (string) $label;

        if (trim($host) === '') {
            return $response->withStatus(400);
        }

        try {
            $domain = $this->domainService->createMarketingDomain($host, $label);
            $this->certificateProvisioner->provisionMarketingDomain($domain['host']);
        } catch (InvalidArgumentException $exception) {
            $message = $translationService?->translate('notify_domain_contact_template_invalid_domain')
                ?? $exception->getMessage();
            $response->getBody()->write(json_encode(['error' => $message]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        }

        $this->refreshMarketingDomainCache();

        $overview = $this->buildDomainOverview(strtolower($request->getUri()->getHost()));

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'domain' => $domain,
            'domains' => $overview['domains'],
            'marketing_domains' => $overview['marketing_domains'],
            'main' => $overview['main'],
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function provisionCertificate(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $domain = isset($data['domain']) ? (string) $data['domain'] : '';
        if ($domain === '') {
            return $response->withStatus(400);
        }

        try {
            $this->certificateProvisioner->provisionMarketingDomain($domain);
        } catch (InvalidArgumentException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $response->getBody()->write(json_encode(['error' => $message !== '' ? $message : 'Certificate request failed.']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'domain' => $domain,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateMarketingDomain(Request $request, Response $response, array $args): Response {
        $translator = $request->getAttribute('translator');
        $translationService = $translator instanceof TranslationService ? $translator : null;

        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $response->withStatus(400);
        }

        $domains = $this->domainService->listMarketingDomains();
        $existing = null;
        foreach ($domains as $domain) {
            if ($domain['id'] === $id) {
                $existing = $domain;
                break;
            }
        }

        if ($existing === null) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $host = isset($data['domain']) ? (string) $data['domain'] : '';
        if ($host === '' && isset($data['host'])) {
            $host = (string) $data['host'];
        }
        if (trim($host) === '') {
            $host = $existing['host'];
        }

        $label = $existing['label'];
        if (array_key_exists('label', $data)) {
            $value = $data['label'];
            $label = $value === null ? null : (string) $value;
        }

        try {
            $domain = $this->domainService->updateMarketingDomain($id, $host, $label);
        } catch (InvalidArgumentException $exception) {
            $message = $translationService?->translate('notify_domain_contact_template_invalid_domain')
                ?? $exception->getMessage();
            $response->getBody()->write(json_encode(['error' => $message]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        }

        if ($domain === null) {
            return $response->withStatus(404);
        }

        $this->refreshMarketingDomainCache();

        $overview = $this->buildDomainOverview(strtolower($request->getUri()->getHost()));

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'domain' => $domain,
            'domains' => $overview['domains'],
            'marketing_domains' => $overview['marketing_domains'],
            'main' => $overview['main'],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function deleteMarketingDomain(Request $request, Response $response, array $args): Response {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $response->withStatus(400);
        }

        $domains = $this->domainService->listMarketingDomains();
        $exists = false;
        foreach ($domains as $domain) {
            if ($domain['id'] === $id) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            return $response->withStatus(404);
        }

        $this->domainService->deleteMarketingDomain($id);

        $this->refreshMarketingDomainCache();

        $overview = $this->buildDomainOverview(strtolower($request->getUri()->getHost()));

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'domains' => $overview['domains'],
            'marketing_domains' => $overview['marketing_domains'],
            'main' => $overview['main'],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function reconcileMarketingDomains(Request $request, Response $response): Response {
        $result = $this->domainService->reconcileMarketingDomains(
            $this->marketingDomainProvider,
            $this->certificateProvisioner
        );

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'provisioned' => $result['provisioned'],
            'marketing_domains' => $this->domainService->listMarketingDomains(),
            'resolved_marketing_domains' => $result['resolved_marketing_domains'],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function refreshMarketingDomainCache(): void
    {
        $this->marketingDomainProvider->clearCache();
    }

    /**
     * @return array<string,string>
     */
    private function buildOptionLabels(?TranslationService $translationService): array {
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

    /**
     * @return array{
     *     domains:list<array{domain:string,normalized:string,type:string,start_page:string,email:?string,smtp_host:?string,smtp_user:?string,smtp_port:?int,smtp_encryption:?string,smtp_dsn:?string,has_smtp_pass:bool}>,
     *     marketing_domains:list<array{id:int,host:string,normalized_host:string,label:?string}>,
     *     main:string
     * }
     */
    private function buildDomainOverview(string $currentHost): array {
        $mainDomain = getenv('MAIN_DOMAIN') ?: '';
        $marketing = getenv('MARKETING_DOMAINS') ?: '';

        $domains = $this->domainService->determineDomains($mainDomain, (string) $marketing, $currentHost);
        $mappings = $this->domainService->getAllMappings();
        $mainNormalized = $this->domainService->normalizeDomain((string) $mainDomain);
        $defaultMain = $this->settingsService->get('home_page', 'help');

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
            $normalizedDomain = $this->domainService->normalizeDomain($domain);
            $candidateKeys = array_values(array_unique(array_filter([
                DomainNameHelper::canonicalizeSlug($normalizedDomain),
                $normalizedDomain,
            ], static fn (string $value): bool => $value !== '')));

            $matchedKey = null;
            foreach ($candidateKeys as $key) {
                if (isset($combined[$key])) {
                    $matchedKey = $key;
                    break;
                }
            }

            if ($matchedKey === null) {
                $matchedKey = $normalizedDomain !== '' ? $normalizedDomain : $domain;
                if (!isset($combined[$matchedKey])) {
                    $combined[$matchedKey] = [
                        'domain' => $normalizedDomain !== '' ? $normalizedDomain : $domain,
                        'normalized' => $matchedKey,
                        'type' => 'custom',
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
            }

            $combined[$matchedKey]['start_page'] = $config['start_page'];
            $combined[$matchedKey]['email'] = $config['email'];
            $combined[$matchedKey]['smtp_host'] = $config['smtp_host'];
            $combined[$matchedKey]['smtp_user'] = $config['smtp_user'];
            $combined[$matchedKey]['smtp_port'] = $config['smtp_port'];
            $combined[$matchedKey]['smtp_encryption'] = $config['smtp_encryption'];
            $combined[$matchedKey]['smtp_dsn'] = $config['smtp_dsn'];
            $combined[$matchedKey]['has_smtp_pass'] = $config['has_smtp_pass'];
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

        return [
            'domains' => $items,
            'marketing_domains' => $this->domainService->listMarketingDomains(),
            'main' => $mainNormalized,
        ];
    }
}
