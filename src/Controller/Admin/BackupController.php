<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

/**
 * Dedicated admin page for application backups.
 */
final class BackupController
{
    /**
     * Render the backup management view.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        return $view->render($response, 'admin/backups.twig', [
            'csrf_token' => $csrf,
            'role' => $_SESSION['user']['role'] ?? '',
            'domainType' => $request->getAttribute('domainType'),
            'currentPath' => $request->getUri()->getPath(),
            'available_namespaces' => $availableNamespaces,
            'default_namespace' => PageService::DEFAULT_NAMESPACE,
            'pageNamespace' => $namespace,
        ]);
    }

    /**
     * Load namespaces for the navigation selector to stay consistent with the admin shell.
     *
     * @return array{0: list<array<string,mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
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
}
