<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\ConfigService;
use App\Service\DesignTokenService;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Service\NamespaceValidator;
use App\Service\PageService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class PagesDesignController
{
    private ConfigService $configService;
    private NamespaceResolver $namespaceResolver;

    public function __construct(
        ?ConfigService $configService = null,
        ?NamespaceResolver $namespaceResolver = null
    ) {
        $pdo = Database::connectFromEnv();
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    public function show(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $designService = $this->getDesignService($request);
        $tokens = $designService->getTokensForNamespace($namespace);
        $defaults = $designService->getDefaults();
        $role = (string) ($_SESSION['user']['role'] ?? '');

        $flash = $_SESSION['page_design_flash'] ?? null;
        unset($_SESSION['page_design_flash']);

        return $view->render($response, 'admin/pages/design.twig', [
            'role' => $role,
            'readOnly' => !$this->isEditRole($role),
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'tokens' => $tokens,
            'tokenDefaults' => $defaults,
            'layoutProfiles' => $designService->getLayoutProfiles(),
            'typographyPresets' => $designService->getTypographyPresets(),
            'cardStyles' => $designService->getCardStyles(),
            'buttonStyles' => $designService->getButtonStyles(),
            'csrf_token' => $this->ensureCsrfToken(),
            'flash' => $flash,
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        $parsedBody = $request->getParsedBody();
        if (!is_array($parsedBody)) {
            return $response->withStatus(400);
        }

        $validator = new NamespaceValidator();
        $namespace = $validator->normalizeCandidate((string) ($parsedBody['namespace'] ?? ''));
        if ($namespace === null) {
            return $response->withStatus(400);
        }

        $role = (string) ($_SESSION['user']['role'] ?? '');
        if (!$this->isEditRole($role)) {
            return $response->withStatus(403);
        }

        $designService = $this->getDesignService($request);
        $defaults = $designService->getDefaults();
        $currentTokens = $designService->getTokensForNamespace($namespace);
        $action = strtolower(trim((string) ($parsedBody['action'] ?? 'save')));

        if ($action === 'reset_all') {
            $designService->resetToDefaults($namespace);
            $message = 'Design auf Standard zurückgesetzt.';
        } else {
            $incoming = $this->extractTokens($parsedBody);
            $tokensToPersist = $currentTokens;
            foreach ($incoming as $group => $values) {
                if (!is_array($values) || !array_key_exists($group, $tokensToPersist)) {
                    continue;
                }
                foreach ($values as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $tokensToPersist[$group][$key] = $value;
                    }
                }
            }

            if ($action === 'reset_brand') {
                $tokensToPersist['brand'] = $defaults['brand'];
            } elseif ($action === 'reset_layout') {
                $tokensToPersist['layout'] = $defaults['layout'];
            } elseif ($action === 'reset_typography') {
                $tokensToPersist['typography'] = $defaults['typography'];
            } elseif ($action === 'reset_components') {
                $tokensToPersist['components'] = $defaults['components'];
            }

            $designService->persistTokens($namespace, $tokensToPersist);
            $message = 'Design-Einstellungen gespeichert.';
        }

        $_SESSION['page_design_flash'] = [
            'type' => 'success',
            'message' => $message,
        ];

        return $response
            ->withHeader('Location', $this->buildRedirectUrl($request, $namespace))
            ->withStatus(303);
    }

    private function ensureCsrfToken(): string
    {
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        return $csrf;
    }

    private function buildRedirectUrl(Request $request, string $namespace): string
    {
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $query = http_build_query(['namespace' => $namespace]);

        return $basePath . '/admin/pages/design' . ($query !== '' ? '?' . $query : '');
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
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

    private function isEditRole(string $role): bool
    {
        return in_array($role, [Roles::ADMIN, Roles::DESIGNER], true);
    }

    private function getDesignService(Request $request): DesignTokenService
    {
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        return new DesignTokenService($pdo, $this->configService);
    }

    /**
     * @param array<string, mixed> $parsedBody
     * @return array<string, array<string, ?string>>
     */
    private function extractTokens(array $parsedBody): array
    {
        $tokens = [
            'brand' => [
                'primary' => null,
                'accent' => null,
            ],
            'layout' => [
                'profile' => null,
            ],
            'typography' => [
                'preset' => null,
            ],
            'components' => [
                'cardStyle' => null,
                'buttonStyle' => null,
            ],
        ];

        $brand = $parsedBody['brand'] ?? [];
        if (is_array($brand)) {
            $tokens['brand']['primary'] = $this->sanitizeString($brand['primary'] ?? null);
            $tokens['brand']['accent'] = $this->sanitizeString($brand['accent'] ?? null);
        }

        $layout = $parsedBody['layout'] ?? [];
        if (is_array($layout)) {
            $tokens['layout']['profile'] = $this->sanitizeString($layout['profile'] ?? null);
        }

        $typography = $parsedBody['typography'] ?? [];
        if (is_array($typography)) {
            $tokens['typography']['preset'] = $this->sanitizeString($typography['preset'] ?? null);
        }

        $components = $parsedBody['components'] ?? [];
        if (is_array($components)) {
            $tokens['components']['cardStyle'] = $this->sanitizeString($components['cardStyle'] ?? null);
            $tokens['components']['buttonStyle'] = $this->sanitizeString($components['buttonStyle'] ?? null);
        }

        return $tokens;
    }

    private function sanitizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
