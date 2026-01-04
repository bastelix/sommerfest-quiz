<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use App\Service\PageService;
use PDO;

/**
 * Resolve namespace-specific design configuration for CMS rendering.
 */
class PagesDesignService
{
    private ConfigService $configService;

    private NamespaceAppearanceService $namespaceAppearance;

    private EffectsPolicyService $effectsPolicy;

    public function __construct(
        ?PDO $pdo = null,
        ?ConfigService $configService = null,
        ?NamespaceAppearanceService $namespaceAppearance = null,
        ?EffectsPolicyService $effectsPolicy = null
    ) {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->namespaceAppearance = $namespaceAppearance ?? new NamespaceAppearanceService();
        $this->effectsPolicy = $effectsPolicy ?? new EffectsPolicyService($this->configService);
    }

    /**
     * @return array{config: array<string,mixed>, appearance: array<string,mixed>, effects: array{effectsProfile: string, sliderProfile: string}, namespace: string}
     */
    public function getDesignForNamespace(string $namespace): array
    {
        $config = $this->configService->getConfigForEvent($namespace);
        if ($config === [] && $namespace !== PageService::DEFAULT_NAMESPACE) {
            $fallbackConfig = $this->configService->getConfigForEvent(PageService::DEFAULT_NAMESPACE);
            if ($fallbackConfig !== []) {
                $config = $fallbackConfig;
            }
        }

        $appearance = $this->namespaceAppearance->load($namespace);
        $effects = $this->effectsPolicy->getEffectsForNamespace($namespace);

        return [
            'config' => $config,
            'appearance' => $appearance,
            'effects' => $effects,
            'namespace' => $namespace,
        ];
    }
}
