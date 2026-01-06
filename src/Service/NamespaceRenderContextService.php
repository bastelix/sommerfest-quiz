<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;

class NamespaceRenderContextService
{
    private DesignTokenService $designTokens;
    private NamespaceAppearanceService $namespaceAppearance;
    private ConfigService $configService;

    public function __construct(
        ?DesignTokenService $designTokens = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
        ?ConfigService $configService = null
    ) {
        $pdo = Database::connectFromEnv();
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->designTokens = $designTokens ?? new DesignTokenService($pdo, $this->configService);
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService(
            $this->designTokens,
            $this->configService
        );
    }

    /**
     * @return array{namespace: string, design: array<string, mixed>}
     */
    public function build(string $namespace): array
    {
        $config = $this->configService->getConfigForEvent($namespace);
        if ($config === [] && $namespace !== PageService::DEFAULT_NAMESPACE) {
            $config = $this->configService->getConfigForEvent(PageService::DEFAULT_NAMESPACE);
        }

        $tokens = $this->designTokens->getTokensForNamespace($namespace);
        $appearance = $this->namespaceAppearance->load($namespace);

        return [
            'namespace' => $namespace,
            'design' => [
                'config' => $config,
                'tokens' => $tokens,
                'appearance' => $appearance,
                'layout' => [
                    'profile' => $this->resolveLayoutProfile($tokens, $appearance),
                ],
                'theme' => $this->resolveTheme($config),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveTheme(array $config): string
    {
        $startTheme = $config['startTheme'] ?? null;
        if (is_string($startTheme)) {
            $normalized = strtolower(trim($startTheme));
            if (in_array($normalized, ['light', 'dark'], true)) {
                return $normalized;
            }
        }

        return 'light';
    }

    /**
     * @param array<string, mixed> $tokens
     * @param array<string, mixed> $appearance
     */
    private function resolveLayoutProfile(array $tokens, array $appearance): ?string
    {
        $layoutTokens = $tokens['layout'] ?? [];
        if (is_array($layoutTokens)) {
            $profile = $layoutTokens['profile'] ?? null;
            if (is_string($profile) && $profile !== '') {
                return $profile;
            }
        }

        $appearanceTokens = $appearance['tokens']['layout'] ?? [];
        if (is_array($appearanceTokens)) {
            $appearanceProfile = $appearanceTokens['profile'] ?? null;
            if (is_string($appearanceProfile) && $appearanceProfile !== '') {
                return $appearanceProfile;
            }
        }

        $defaultLayout = $appearance['defaults']['layout'] ?? [];
        if (is_array($defaultLayout)) {
            $defaultProfile = $defaultLayout['profile'] ?? null;
            if (is_string($defaultProfile) && $defaultProfile !== '') {
                return $defaultProfile;
            }
        }

        return null;
    }
}
