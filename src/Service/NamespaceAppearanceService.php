<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;

class NamespaceAppearanceService
{
    private DesignTokenService $designTokens;

    public function __construct(?DesignTokenService $designTokens = null)
    {
        $pdo = Database::connectFromEnv();
        $this->designTokens = $designTokens ?? new DesignTokenService($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $namespace): array
    {
        $tokens = $this->designTokens->getTokensForNamespace($namespace);

        return [
            'tokens' => $tokens,
            'defaults' => $this->designTokens->getDefaults(),
            'colors' => [
                'primary' => $tokens['brand']['primary'] ?? null,
                'secondary' => $tokens['brand']['accent'] ?? null,
                'accent' => $tokens['brand']['accent'] ?? null,
            ],
        ];
    }
}
