<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Infrastructure\MailProviderRepository;
use App\Service\LandingNewsService;
use App\Service\NewsletterCampaignSender;
use App\Service\NewsletterCampaignService;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Repository\NamespaceRepository;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

class NewsletterCampaignController
{
    private NewsletterCampaignService $campaigns;

    private LandingNewsService $landingNews;

    private MailProviderRepository $providers;

    private NamespaceResolver $namespaceResolver;

    private NamespaceRepository $namespaceRepository;

    public function __construct(
        ?NewsletterCampaignService $campaigns = null,
        ?LandingNewsService $landingNews = null,
        ?MailProviderRepository $providers = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceRepository $namespaceRepository = null
    ) {
        $pdo = Database::connectFromEnv();
        $this->campaigns = $campaigns ?? new NewsletterCampaignService($pdo);
        $this->landingNews = $landingNews ?? new LandingNewsService($pdo);
        $this->providers = $providers ?? new MailProviderRepository($pdo);
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceRepository = $namespaceRepository ?? new NamespaceRepository($pdo);
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $campaigns = $this->campaigns->getAll($namespace);
        $queryParams = $request->getQueryParams();
        $editId = isset($queryParams['edit']) ? (int) $queryParams['edit'] : null;
        $activeCampaign = $editId !== null ? $this->campaigns->find((int) $editId) : null;
        $newsEntries = $this->landingNews->getAllForNamespace($namespace);
        $csrf = $this->ensureCsrfToken();

        return $view->render($response, 'admin/newsletter_campaigns.twig', [
            'pageTab' => 'newsletter-campaigns',
            'campaigns' => $campaigns,
            'activeCampaign' => $activeCampaign,
            'newsEntries' => $newsEntries,
            'pageNamespace' => $namespace,
            'csrf_token' => $csrf,
            'available_namespaces' => $availableNamespaces,
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'error_message' => isset($queryParams['error']) ? (string) $queryParams['error'] : null,
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $csrfToken = (string) ($data['csrf_token'] ?? '');
        if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
            throw new RuntimeException('Invalid CSRF token.');
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $name = (string) ($data['name'] ?? '');
        $newsIds = isset($data['news_ids']) ? (array) $data['news_ids'] : [];
        $templateId = isset($data['template_id']) ? (string) $data['template_id'] : null;
        $audienceId = isset($data['audience_id']) ? (string) $data['audience_id'] : null;
        $status = (string) ($data['status'] ?? 'draft');
        $scheduledRaw = isset($data['scheduled_for']) ? (string) $data['scheduled_for'] : '';
        $scheduledFor = $scheduledRaw !== '' ? new DateTimeImmutable($scheduledRaw) : null;

        if ($id === null) {
            $this->campaigns->create($namespace, $name, $newsIds, $templateId, $audienceId, $status, $scheduledFor);
        } else {
            $this->campaigns->update($id, $namespace, $name, $newsIds, $templateId, $audienceId, $status, $scheduledFor);
        }

        $query = $namespace !== '' ? '?namespace=' . rawurlencode($namespace) : '';

        return $response->withHeader('Location', $request->getAttribute('basePath') . '/admin/newsletter' . $query)
            ->withStatus(302);
    }

    public function send(Request $request, Response $response, array $args): Response
    {
        $data = (array) $request->getParsedBody();
        $csrfToken = (string) ($data['csrf_token'] ?? '');
        if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
            throw new RuntimeException('Invalid CSRF token.');
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $campaignId = isset($args['id']) ? (int) $args['id'] : 0;
        $campaign = $this->campaigns->find($campaignId);
        if ($campaign === null || $campaign->getNamespace() !== $namespace) {
            throw new RuntimeException('Campaign not found.');
        }

        $sender = new NewsletterCampaignSender($this->campaigns, $this->landingNews, $this->providers);
        $basePath = (string) ($request->getAttribute('basePath') ?? '');
        $query = $namespace !== '' ? '?namespace=' . rawurlencode($namespace) : '';
        $baseLocation = (string) $request->getAttribute('basePath') . '/admin/newsletter' . $query;

        try {
            $sender->send($campaign, $basePath);
        } catch (RuntimeException $exception) {
            $this->campaigns->markFailed($campaign->getId());
            $separator = str_contains($baseLocation, '?') ? '&' : '?';

            return $response
                ->withHeader('Location', $baseLocation . $separator . 'error=' . rawurlencode($exception->getMessage()))
                ->withStatus(302);
        }

        return $response->withHeader('Location', $baseLocation)
            ->withStatus(302);
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
        } catch (RuntimeException $exception) {
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
