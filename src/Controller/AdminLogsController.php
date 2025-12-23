<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LogService;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AdminLogsController
{
    /**
     * Display recent application logs.
     */
    public function __invoke(Request $request, Response $response): Response {
        $appLog = LogService::tail('app');
        $stripeLog = LogService::tail('stripe');
        $slimLog = LogService::tailDocker('slim-1');
        $view = Twig::fromRequest($request);
        $role = $_SESSION['user']['role'] ?? '';
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        return $view->render($response, 'admin/logs.twig', [
            'appLog' => $appLog,
            'stripeLog' => $stripeLog,
            'slimLog' => $slimLog,
            'role' => $role,
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
        ]);
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowedNamespaces = $accessService->resolveAllowedNamespaces(is_string($role) ? $role : null);
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $repository = new NamespaceRepository($pdo);
        try {
            $availableNamespaces = $repository->list();
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

        if (!array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === $namespace
        ) && $accessService->shouldExposeNamespace($namespace, $allowedNamespaces, $role)) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => null,
                'is_active' => true,
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
}
