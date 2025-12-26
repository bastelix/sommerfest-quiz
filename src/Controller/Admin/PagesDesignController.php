<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Service\ConfigService;
use App\Service\ImageUploadService;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Repository\NamespaceRepository;
use App\Service\PageService;
use App\Service\NamespaceValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use PDO;

class PagesDesignController
{
    private ConfigService $configService;
    private ImageUploadService $imageUploadService;
    private NamespaceResolver $namespaceResolver;

    public function __construct(
        ?ConfigService $configService = null,
        ?ImageUploadService $imageUploadService = null,
        ?NamespaceResolver $namespaceResolver = null
    ) {
        $pdo = Database::connectFromEnv();
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->imageUploadService = $imageUploadService ?? new ImageUploadService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    public function show(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $config = $this->configService->getConfigForEvent($namespace);

        $config['colors'] = $this->mergeColorConfig($config);

        $flash = $_SESSION['page_design_flash'] ?? null;
        unset($_SESSION['page_design_flash']);

        return $view->render($response, 'admin/pages/design.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'config' => $config,
            'csrf_token' => $this->ensureCsrfToken(),
            'flash' => $flash,
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        $parsedBody = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        if (!is_array($parsedBody)) {
            return $response->withStatus(400);
        }

        $validator = new NamespaceValidator();
        $namespace = $validator->normalizeCandidate((string) ($parsedBody['namespace'] ?? ''));
        if ($namespace === null) {
            return $response->withStatus(400);
        }

        $colors = [
            'primary' => $this->normalizeColor($parsedBody['primaryColor'] ?? null),
            'background' => $this->normalizeColor($parsedBody['backgroundColor'] ?? null),
            'accent' => $this->normalizeColor($parsedBody['accentColor'] ?? null),
        ];

        $logoPath = null;
        $logoUrl = isset($parsedBody['logoUrl']) ? trim((string) $parsedBody['logoUrl']) : '';
        $uploadedLogo = $files['logo'] ?? null;

        try {
            $this->assertValidColors($colors);

            if ($uploadedLogo !== null && $uploadedLogo->getError() !== UPLOAD_ERR_NO_FILE) {
                $this->imageUploadService->validate(
                    $uploadedLogo,
                    5 * 1024 * 1024,
                    ['png', 'jpg', 'jpeg', 'webp', 'svg'],
                    ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']
                );
                $logoPath = $this->imageUploadService->saveUploadedFile(
                    $uploadedLogo,
                    'uploads',
                    'logo-' . $namespace,
                    512,
                    512,
                    ImageUploadService::QUALITY_LOGO,
                    true
                );
            } elseif ($logoUrl !== '') {
                if (!filter_var($logoUrl, FILTER_VALIDATE_URL)) {
                    throw new \RuntimeException('invalid logo url');
                }
                $logoPath = $logoUrl;
            }
        } catch (\RuntimeException $exception) {
            $_SESSION['page_design_flash'] = [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ];

            return $response
                ->withHeader('Location', $this->buildRedirectUrl($request, $namespace))
                ->withStatus(303);
        }

        $config = $this->configService->getConfigForEvent($namespace);
        $config['event_uid'] = $namespace;

        $colorPayload = array_filter(
            [
                'primary' => $colors['primary'],
                'accent' => $colors['accent'],
                'background' => $colors['background'],
            ],
            static fn (?string $value): bool => $value !== null && $value !== ''
        );

        if ($colorPayload !== []) {
            $config['colors'] = $colorPayload;
        }

        if ($colors['background'] !== null) {
            $config['backgroundColor'] = $colors['background'];
        }

        if ($colors['accent'] !== null) {
            $config['buttonColor'] = $colors['accent'];
        }

        if ($logoPath !== null) {
            $config['logoPath'] = $logoPath;
        }

        $this->configService->saveConfig($config);

        $_SESSION['page_design_flash'] = [
            'type' => 'success',
            'message' => 'Design-Einstellungen gespeichert.',
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

    private function normalizeColor(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $normalized = strtolower($trimmed);
        if (!str_starts_with($normalized, '#')) {
            $normalized = '#' . $normalized;
        }

        return $normalized;
    }

    private function assertValidColors(array $colors): void
    {
        $pattern = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';
        foreach ($colors as $color) {
            if ($color === null || $color === '') {
                continue;
            }

            if (!preg_match($pattern, $color)) {
                throw new \RuntimeException('Ungültiges Farbformat. Nutze Hex-Werte wie #336699.');
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, string>
     */
    private function mergeColorConfig(array $config): array
    {
        $colors = [];

        if (isset($config['colors']) && is_array($config['colors'])) {
            foreach ($config['colors'] as $key => $value) {
                if (is_string($value) && $value !== '') {
                    $colors[$key] = $value;
                }
            }
        }

        if (isset($config['backgroundColor']) && !isset($colors['background']) && is_string($config['backgroundColor'])) {
            $colors['background'] = $config['backgroundColor'];
        }

        if (isset($config['backgroundColor']) && !isset($colors['primary']) && is_string($config['backgroundColor'])) {
            $colors['primary'] = $config['backgroundColor'];
        }

        if (isset($config['buttonColor']) && !isset($colors['accent']) && is_string($config['buttonColor'])) {
            $colors['accent'] = $config['buttonColor'];
        }

        return $colors;
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
}

