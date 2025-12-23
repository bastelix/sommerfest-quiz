<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\NamespaceAccessService;
use App\Service\MarketingNewsletterConfigService;
use App\Service\NamespaceResolver;
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

    public function __construct(
        ?PDO $pdo = null,
        ?MarketingNewsletterConfigService $newsletterService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceRepository $namespaceRepository = null
    ) {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->newsletterService = $newsletterService ?? new MarketingNewsletterConfigService($pdo);
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceRepository = $namespaceRepository ?? new NamespaceRepository($pdo);
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $marketingNewsletterConfigs = $this->newsletterService->getAllGrouped($namespace);
        $marketingNewsletterSlugs = array_keys($marketingNewsletterConfigs);
        sort($marketingNewsletterSlugs);

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
        ]);
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
            $availableNamespaces = $this->namespaceRepository->list();
        } catch (\RuntimeException $exception) {
            $availableNamespaces = [];
        }

        if ($accessService->shouldExposeNamespace(PageService::DEFAULT_NAMESPACE, $allowedNamespaces, $role)
            && !array_filter(
                $availableNamespaces,
                static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
            )) {
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
        if (!$currentNamespaceExists
            && $accessService->shouldExposeNamespace($namespace, $allowedNamespaces, $role)) {
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
                if (!array_filter(
                    $availableNamespaces,
                    static fn (array $entry): bool => $entry['namespace'] === $allowedNamespace
                )) {
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
