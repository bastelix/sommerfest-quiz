<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\NamespaceResolver;
use App\Service\DomainService;
use App\Service\PageService;
use App\Service\UrlService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

/**
 * Dedicated admin page for managing custom domains.
 */
final class DomainPageController
{
    /**
     * Render the domain management view.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        [$availableNamespaces, $namespace] = $this->loadNamespaces($request, $pdo);
        $domains = $this->loadDomains($pdo);

        $baseUrl = UrlService::determineBaseUrl($request);
        $mainDomain = getenv('MAIN_DOMAIN')
            ?: getenv('DOMAIN')
            ?: $request->getUri()->getHost();

        return $view->render($response, 'admin/domains.twig', [
            'csrf_token' => $csrf,
            'role' => $_SESSION['user']['role'] ?? '',
            'domainType' => $request->getAttribute('domainType'),
            'currentPath' => $request->getUri()->getPath(),
            'available_namespaces' => $availableNamespaces,
            'default_namespace' => PageService::DEFAULT_NAMESPACE,
            'pageNamespace' => $namespace,
            'domains' => $domains,
            'baseUrl' => $baseUrl,
            'main_domain' => $mainDomain,
        ]);
    }

    /**
     * Load namespaces for the navigation selector and the domain form.
     *
     * @return array{0: list<array<string,mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request, PDO $pdo): array
    {
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $repository = new NamespaceRepository($pdo);
        try {
            $availableNamespaces = $repository->listActive();
        } catch (RuntimeException) {
            $availableNamespaces = [];
        }

        $hasDefault = array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
        );
        if (!$hasDefault) {
            $availableNamespaces[] = [
                'namespace' => PageService::DEFAULT_NAMESPACE,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        $currentExists = array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === $namespace
        );
        if (!$currentExists) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => 'nicht gespeichert',
                'is_active' => false,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [$availableNamespaces, $namespace];
    }

    /**
     * Load existing domains so the view can render a server-side fallback table.
     *
     * @return list<array{id:int,host:string,normalized_host:string,namespace:?string,label:?string,is_active:bool}>
     */
    private function loadDomains(PDO $pdo): array
    {
        try {
            $domainService = new DomainService($pdo);

            return $domainService->listDomains(includeInactive: true);
        } catch (\Throwable) {
            return [];
        }
    }
}
