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
    public static function apply(string $html): string
    {
        $pdo = Database::connectFromEnv();
        $profile = (new TenantService($pdo))->getMainTenant();

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
