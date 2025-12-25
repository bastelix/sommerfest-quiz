<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\ConfigService;
use App\Service\ImageUploadService;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Service\NamespaceService;
use App\Service\PageService;
use App\Service\TenantService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Throwable;

final class DesignController
{
    private ConfigService $configService;
    private ImageUploadService $imageUploadService;
    private NamespaceResolver $namespaceResolver;
    private NamespaceService $namespaceService;
    private TenantService $tenantService;

    public function __construct(
        ?ConfigService $configService = null,
        ?ImageUploadService $imageUploadService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceService $namespaceService = null,
        ?TenantService $tenantService = null,
        ?NamespaceRepository $namespaceRepository = null
    ) {
        $this->configService = $configService ?? new ConfigService(Database::connectFromEnv());
        $this->imageUploadService = $imageUploadService ?? new ImageUploadService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceService = $namespaceService ?? new NamespaceService($namespaceRepository ?? new NamespaceRepository());
        $this->tenantService = $tenantService ?? new TenantService();
    }

    public function show(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $design = $this->resolveDesignSettings();
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());

        return $view->render($response, 'admin/pages/design.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'csrf_token' => $this->ensureCsrfToken(),
            'design' => $design,
            'pageTab' => 'design',
            'tenant' => $this->resolveTenant($request),
            'basePath' => $basePath,
            'saved' => (string)($request->getQueryParams()['saved'] ?? '') === '1',
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            return $response->withStatus(400);
        }

        $colors = $this->normalizePalette($payload);
        $startTheme = strtolower(trim((string)($payload['startTheme'] ?? 'light')));
        if (!in_array($startTheme, ['light', 'dark'], true)) {
            $startTheme = 'light';
        }

        $config = $this->configService->getConfig();
        $data = [
            'colors' => $colors,
            'startTheme' => $startTheme,
        ];
        if (isset($config['event_uid'])) {
            $data['event_uid'] = $config['event_uid'];
        }

        $files = $request->getUploadedFiles();
        $logo = $files['logo'] ?? null;
        if ($logo !== null && $logo->getError() !== UPLOAD_ERR_NO_FILE) {
            try {
                $this->imageUploadService->validate(
                    $logo,
                    5 * 1024 * 1024,
                    ['png', 'webp', 'svg'],
                    ['image/png', 'image/webp', 'image/svg+xml']
                );
                $logoPath = $this->imageUploadService->saveUploadedFile(
                    $logo,
                    'design',
                    'logo',
                    512,
                    512,
                    ImageUploadService::QUALITY_LOGO,
                    true
                );
                $data['logoPath'] = $logoPath;
            } catch (Throwable $exception) {
                $response->getBody()->write($exception->getMessage());

                return $response->withHeader('Content-Type', 'text/plain')->withStatus(400);
            }
        }

        $this->configService->saveConfig($data);

        $redirectPath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath())
            . '/admin/pages/design?saved=1';

        return $response
            ->withHeader('Location', $redirectPath)
            ->withStatus(302);
    }

    private function normalizePalette(array $payload): array
    {
        $config = $this->configService->getConfig();
        $existingColors = $config['colors'] ?? [];

        $lightPrimary = $this->normalizeColor(
            $payload['color_light_primary'] ?? $existingColors['light']['primary'] ?? $existingColors['primary'] ?? $config['buttonColor'] ?? '#1e87f0'
        );
        $lightSecondary = $this->normalizeColor(
            $payload['color_light_secondary'] ?? $existingColors['light']['secondary'] ?? $existingColors['accent'] ?? '#222222',
            '#222222'
        );
        $darkPrimary = $this->normalizeColor(
            $payload['color_dark_primary'] ?? $existingColors['dark']['primary'] ?? $existingColors['primary'] ?? '#0f172a',
            '#0f172a'
        );
        $darkSecondary = $this->normalizeColor(
            $payload['color_dark_secondary'] ?? $existingColors['dark']['secondary'] ?? $existingColors['accent'] ?? '#93c5fd',
            '#93c5fd'
        );

        return [
            'primary' => $lightPrimary,
            'accent' => $lightSecondary,
            'light' => [
                'primary' => $lightPrimary,
                'secondary' => $lightSecondary,
            ],
            'dark' => [
                'primary' => $darkPrimary,
                'secondary' => $darkSecondary,
            ],
        ];
    }

    private function normalizeColor(mixed $value, string $default = '#1e87f0'): string
    {
        $candidate = strtolower(trim((string)$value));
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $candidate)) {
            return $default;
        }

        if (strlen($candidate) === 4) {
            $candidate = '#' . $candidate[1] . $candidate[1] . $candidate[2] . $candidate[2] . $candidate[3] . $candidate[3];
        }

        return $candidate;
    }

    private function resolveDesignSettings(): array
    {
        $config = $this->configService->getConfig();
        $colors = $config['colors'] ?? [];
        $lightColors = $colors['light'] ?? [];
        $darkColors = $colors['dark'] ?? [];

        $startTheme = strtolower(trim((string)($config['startTheme'] ?? 'light')));
        if (!in_array($startTheme, ['light', 'dark'], true)) {
            $startTheme = 'light';
        }

        return [
            'startTheme' => $startTheme,
            'colors' => [
                'light' => [
                    'primary' => $lightColors['primary'] ?? $colors['primary'] ?? $config['buttonColor'] ?? '#1e87f0',
                    'secondary' => $lightColors['secondary'] ?? $colors['accent'] ?? '#222222',
                ],
                'dark' => [
                    'primary' => $darkColors['primary'] ?? $colors['primary'] ?? '#0f172a',
                    'secondary' => $darkColors['secondary'] ?? $colors['accent'] ?? '#93c5fd',
                ],
            ],
            'logoPath' => $config['logoPath'] ?? null,
        ];
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

    private function loadNamespaces(Request $request): array
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowedNamespaces = $accessService->resolveAllowedNamespaces(is_string($role) ? $role : null);

        try {
            $availableNamespaces = $this->namespaceService->all();
        } catch (Throwable) {
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

    private function resolveTenant(Request $request): ?array
    {
        $domainType = (string) $request->getAttribute('domainType');
        if ($domainType === 'main') {
            return $this->tenantService->getMainTenant();
        }

        $host = $request->getUri()->getHost();
        $subdomain = explode('.', $host)[0];
        if ($subdomain === '') {
            return null;
        }

        return $this->tenantService->getBySubdomain($subdomain);
    }
}
