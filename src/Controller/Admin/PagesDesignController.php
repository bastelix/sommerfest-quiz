<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\ConfigService;
use App\Service\DesignTokenService;
use App\Service\EffectsPolicyService;
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
        $designPayload = $this->configService->resolveDesignConfig($namespace);
        $config = $designPayload['config'];
        $marketingScheme = null;
        $appearanceColors = [];
        if (is_array($config['colors'] ?? null)) {
            $marketingScheme = $config['colors']['marketingScheme']
                ?? $config['colors']['marketing_scheme']
                ?? null;
            $appearanceColors = [
                'textOnSurface' => $config['colors']['textOnSurface']
                    ?? $config['colors']['text_on_surface']
                    ?? null,
                'textOnBackground' => $config['colors']['textOnBackground']
                    ?? $config['colors']['text_on_background']
                    ?? null,
                'textOnPrimary' => $config['colors']['textOnPrimary']
                    ?? $config['colors']['text_on_primary']
                    ?? $config['colors']['onAccent']
                    ?? $config['colors']['on_accent']
                    ?? $config['colors']['onPrimary']
                    ?? $config['colors']['on_primary']
                    ?? $config['colors']['contrastOnPrimary']
                    ?? null,
            ];
        }
        $effectsService = new EffectsPolicyService($this->configService);
        $effectsDefaults = $effectsService->getDefaults();
        $effects = $effectsService->getEffectsForNamespace($namespace);
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $canAccessBehavior = $role !== Roles::REDAKTEUR;
        $activeTab = $this->resolveActiveTab($request, $canAccessBehavior);

        $flash = $_SESSION['page_design_flash'] ?? null;
        unset($_SESSION['page_design_flash']);

        return $view->render($response, 'admin/pages/design.twig', [
            'role' => $role,
            'readOnly' => !$this->isEditRole($role),
            'effectsReadOnly' => !$this->isEditRole($role),
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'tokens' => $tokens,
            'tokenDefaults' => $defaults,
            'marketingScheme' => $marketingScheme,
            'appearanceColors' => $appearanceColors,
            'effects' => $effects,
            'effectsDefaults' => $effectsDefaults,
            'effectsProfiles' => $effectsService->getProfiles(),
            'hasSliderBlocks' => $effectsService->hasSliderBlocks(),
            'layoutProfiles' => $designService->getLayoutProfiles(),
            'typographyPresets' => $designService->getTypographyPresets(),
            'cardStyles' => $designService->getCardStyles(),
            'buttonStyles' => $designService->getButtonStyles(),
            'activeTab' => $activeTab,
            'csrf_token' => $this->ensureCsrfToken(),
            'flash' => $flash,
            'canAccessBehavior' => $canAccessBehavior,
            'designUsedDefaults' => $designPayload['usedDefaults'],
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        /** @var mixed $parsedBody */
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
        $effectsService = new EffectsPolicyService($this->configService);
        $action = strtolower(trim((string) ($parsedBody['action'] ?? 'save')));

        if ($action === 'reset_all') {
            $designService->resetToDefaults($namespace);
            $message = 'Design auf Standard zurückgesetzt.';
        } elseif ($action === 'save_effects') {
            $effects = $this->extractEffects($parsedBody);
            $effectsService->persist($namespace, $effects);
            $message = 'Verhalten-Einstellungen gespeichert.';
        } else {
            $incoming = $this->extractTokens($parsedBody);
            [$hasAppearanceVariables, $appearanceVariables] = $this->extractAppearanceVariables($parsedBody);
            /** @var array{marketingScheme?: ?string, textOnSurface?: ?string, textOnBackground?: ?string, textOnPrimary?: ?string} $appearanceVariables */
            $tokensToPersist = $currentTokens;
            foreach ($incoming as $group => $values) {
                if (!array_key_exists($group, $tokensToPersist)) {
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

            $config = [];
            $currentMarketingScheme = null;
            if ($hasAppearanceVariables) {
                $config = $this->configService->getConfigForEvent($namespace);
                if (is_array($config['colors'] ?? null)) {
                    $currentMarketingScheme = $config['colors']['marketingScheme']
                        ?? $config['colors']['marketing_scheme']
                        ?? null;
                }
            }

            $designService->persistTokens($namespace, $tokensToPersist);
            if ($hasAppearanceVariables) {
                $colors = is_array($config['colors'] ?? null) ? $config['colors'] : [];
                if (array_key_exists('marketingScheme', $appearanceVariables)) {
                    $marketingScheme = $appearanceVariables['marketingScheme'];
                    if ($marketingScheme === null) {
                        unset($colors['marketingScheme'], $colors['marketing_scheme']);
                    } else {
                        $colors['marketingScheme'] = $marketingScheme;
                        unset($colors['marketing_scheme']);
                    }
                }
                foreach (['textOnSurface', 'textOnBackground', 'textOnPrimary'] as $key) {
                    if (!array_key_exists($key, $appearanceVariables)) {
                        continue;
                    }
                    if ($appearanceVariables[$key] === null) {
                        unset($colors[$key]);
                    } else {
                        $colors[$key] = $appearanceVariables[$key];
                    }
                }
                $this->configService->saveConfig([
                    'event_uid' => $namespace,
                    'colors' => $colors,
                ]);
            }
            $message = 'Design-Einstellungen gespeichert.';
        }

        $_SESSION['page_design_flash'] = [
            'type' => 'success',
            'message' => $message,
        ];

        return $response
            ->withHeader('Location', $this->buildRedirectUrl($request, $namespace, $action === 'save_effects' ? 'behavior' : null))
            ->withStatus(303);
    }

    private function ensureCsrfToken(): string
    {
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        return $csrf;
    }

    private function buildRedirectUrl(Request $request, string $namespace, ?string $tab = null): string
    {
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $queryData = ['namespace' => $namespace];
        if ($tab !== null) {
            $queryData['tab'] = $tab;
        }

        $query = http_build_query($queryData);

        return $basePath . '/admin/pages/design' . ($query !== '' ? '?' . $query : '');
    }

    private function resolveActiveTab(Request $request, bool $canAccessBehavior): string
    {
        $tab = strtolower(trim((string) ($request->getQueryParams()['tab'] ?? '')));
        if ($canAccessBehavior && $tab === 'behavior') {
            return 'behavior';
        }

        return 'appearance';
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
                'secondary' => null,
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
            $tokens['brand']['secondary'] = $this->sanitizeString($brand['secondary'] ?? null);
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

    /**
     * @param array<string, mixed> $parsedBody
     * @return array{0: bool, 1: array{marketingScheme?: ?string, textOnSurface?: ?string, textOnBackground?: ?string, textOnPrimary?: ?string}}
     */
    private function extractAppearanceVariables(array $parsedBody): array
    {
        $appearance = $parsedBody['appearance'] ?? null;
        if (!is_array($appearance)) {
            return [false, []];
        }
        $variables = $appearance['variables'] ?? null;
        if (!is_array($variables)) {
            return [false, []];
        }

        $allowedKeys = ['marketingScheme', 'textOnSurface', 'textOnBackground', 'textOnPrimary'];
        $result = [];
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $variables)) {
                continue;
            }
            if ($key === 'marketingScheme') {
                $result[$key] = $this->normalizeMarketingScheme($variables[$key]);
            } else {
                $result[$key] = $this->sanitizeString($variables[$key]);
            }
        }

        if ($result === []) {
            return [false, []];
        }

        return [true, $result];
    }

    private function normalizeMarketingScheme(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = strtolower($trimmed);
        if ($normalized === 'monochrom') {
            $normalized = 'monochrome';
        }

        $allowedSchemes = $this->getMarketingSchemeWhitelist();
        if ($allowedSchemes !== [] && !in_array($normalized, $allowedSchemes, true)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function getMarketingSchemeWhitelist(): array
    {
        $path = dirname(__DIR__, 3) . '/config/marketing-design-tokens.php';
        if (!is_file($path)) {
            return [];
        }

        $schemes = require $path;
        if (!is_array($schemes)) {
            return [];
        }

        return array_keys($schemes);
    }

    /**
     * @param array<string, mixed> $parsedBody
     * @return array{effectsProfile: ?string, sliderProfile: ?string}
     */
    private function extractEffects(array $parsedBody): array
    {
        $effectsProfile = null;
        $sliderProfile = null;

        if (isset($parsedBody['effects_profile'])) {
            $effectsProfile = $this->sanitizeString($parsedBody['effects_profile']);
        }

        if (isset($parsedBody['slider_profile'])) {
            $sliderProfile = $this->sanitizeString($parsedBody['slider_profile']);
        }

        return [
            'effectsProfile' => $effectsProfile,
            'sliderProfile' => $sliderProfile,
        ];
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
