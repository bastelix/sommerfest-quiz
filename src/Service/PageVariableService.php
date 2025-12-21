<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use App\Service\TenantService;

/**
 * Replaces placeholder variables in HTML content with profile data.
 */
class PageVariableService
{
    /**
     * Apply profile-based replacements on the given HTML.
     */
    public static function apply(string $html, ?string $namespace = null): string {
        $namespace = $namespace !== null && $namespace !== ''
            ? $namespace
            : PageService::DEFAULT_NAMESPACE;

        try {
            $pdo = Database::connectFromEnv();
            $profile = (new TenantService($pdo))->getNamespaceProfile($namespace);
        } catch (\Throwable $e) {
            $file = dirname(__DIR__, 2) . '/data/profile.json';
            $profile = is_readable($file)
                ? json_decode((string) file_get_contents($file), true) ?: []
                : [];
        }

        $replacements = [
            '[NAME]' => $profile['imprint_name'] ?? '',
            '[STREET]' => $profile['imprint_street'] ?? '',
            '[ZIP]' => $profile['imprint_zip'] ?? '',
            '[CITY]' => $profile['imprint_city'] ?? '',
            '[EMAIL]' => $profile['imprint_email'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }
}
