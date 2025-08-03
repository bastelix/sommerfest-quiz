<?php

declare(strict_types=1);

namespace App\Service;

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
        $path = dirname(__DIR__, 2) . '/data/profile.json';
        $profile = [];
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                $profile = $data;
            }
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
