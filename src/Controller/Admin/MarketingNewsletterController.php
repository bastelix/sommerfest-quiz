<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Infrastructure\MailProviderRepository;
use App\Repository\NamespaceRepository;
use App\Service\LandingNewsService;
use App\Service\NamespaceAccessService;
use App\Service\MarketingNewsletterConfigService;
use App\Service\NamespaceResolver;
use App\Service\NewsletterCampaignService;
use App\Service\PageService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class MarketingNewsletterController
{
    private MarketingNewsletterConfigService $newsletterService;
    private NamespaceResolver $namespaceResolver;
    private NamespaceRepository $namespaceRepository;
    private NewsletterCampaignService $campaignService;
    private LandingNewsService $landingNews;
    private MailProviderRepository $providers;

    public function __construct(
        ?PDO $pdo = null,
        ?MarketingNewsletterConfigService $newsletterService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceRepository $namespaceRepository = null,
        ?NewsletterCampaignService $campaignService = null,
        ?LandingNewsService $landingNews = null,
        ?MailProviderRepository $providers = null
    ) {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->newsletterService = $newsletterService ?? new MarketingNewsletterConfigService($pdo);
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceRepository = $namespaceRepository ?? new NamespaceRepository($pdo);
        $this->campaignService = $campaignService ?? new NewsletterCampaignService($pdo);
        $this->landingNews = $landingNews ?? new LandingNewsService($pdo);
        $this->providers = $providers ?? new MailProviderRepository($pdo);
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $marketingNewsletterConfigs = $this->newsletterService->getAllGrouped($namespace);
        $marketingNewsletterSlugs = array_keys($marketingNewsletterConfigs);
        sort($marketingNewsletterSlugs);

        $queryParams = $request->getQueryParams();
        $campaigns = $this->campaignService->getAll($namespace);
        $editId = isset($queryParams['edit']) ? (int) $queryParams['edit'] : null;
        $activeCampaign = $editId !== null ? $this->campaignService->find($editId) : null;
        $newsEntries = $this->landingNews->getAllForNamespace($namespace);

        $providerDefaults = $this->loadProviderDefaults($namespace);

        return $view->render($response, 'admin/newsletter.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'marketingNewsletterConfigs' => $marketingNewsletterConfigs,
            'marketingNewsletterSlugs' => $marketingNewsletterSlugs,
            'marketingNewsletterStyles' => $this->newsletterService->getAllowedStyles(),
            'csrf_token' => $this->ensureCsrfToken(),
            'pageTab' => 'newsletter',
            'campaigns' => $campaigns,
            'activeCampaign' => $activeCampaign,
            'newsEntries' => $newsEntries,
            'providerDefaults' => $providerDefaults,
            'error_message' => isset($queryParams['error']) ? (string) $queryParams['error'] : null,
            'activeTab' => (string) ($queryParams['tab'] ?? 'campaigns'),
        ]);
    }

    /**
     * @return array{template_id: string|null, audience_id: string|null}
     */
    private function loadProviderDefaults(string $namespace): array
    {
        $defaults = ['template_id' => null, 'audience_id' => null];
        try {
            $provider = $this->providers->findActive($namespace);
            if ($provider !== null) {
                $settings = $provider['settings'] ?? [];
                $defaults['template_id'] = isset($settings['default_template_id']) ? (string) $settings['default_template_id'] : null;
                $defaults['audience_id'] = $provider['list_id'] ?? null;
            }
        } catch (\Throwable $e) {
            // Provider may not be configured; leave defaults as null
        }

        return $defaults;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowedNamespaces = $accessService->resolveAllowedNamespaces(is_string($role) ? $role : null);

        try {
            $availableNamespaces = $this->namespaceRepository->listActive();
        } catch (\RuntimeException $exception) {
            $availableNamespaces = [];
        }

        if (
            $accessService->shouldExposeNamespace(PageService::DEFAULT_NAMESPACE, $allowedNamespaces, $role)
            && !array_filter(
                $availableNamespaces,
                static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
            )
        ) {
            $availableNamespaces[] = [
                'namespace' => PageService::DEFAULT_NAMESPACE,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        $currentNamespaceExists = array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === $namespace
        );
        if (
            !$currentNamespaceExists
            && $accessService->shouldExposeNamespace($namespace, $allowedNamespaces, $role)
        ) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => 'nicht gespeichert',
                'is_active' => false,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        if ($allowedNamespaces !== []) {
            foreach ($allowedNamespaces as $allowedNamespace) {
                if (
                    !array_filter(
                        $availableNamespaces,
                        static fn (array $entry): bool => $entry['namespace'] === $allowedNamespace
                    )
                ) {
                    $availableNamespaces[] = [
                        'namespace' => $allowedNamespace,
                        'label' => 'nicht gespeichert',
                        'is_active' => false,
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                }
            }
        }

        $availableNamespaces = $accessService->filterNamespaceEntries($availableNamespaces, $allowedNamespaces, $role);

        return [$availableNamespaces, $namespace];
    }

    private function ensureCsrfToken(): string
    {
        $token = $_SESSION['csrf_token'] ?? '';
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $_SESSION['csrf_token'] = $token;
        }

        return $token;
    }
}
