<?php

declare(strict_types=1);

namespace App\Application\Seo;

/**
 * Validates SEO input data.
 */
class SeoValidator
{
    public const TITLE_MAX_LENGTH = 60;
    public const DESCRIPTION_MAX_LENGTH = 160;

    /**
     * Validate SEO form data.
     *
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    public function validate(array $data): array {
        $errors = [];

        $slug = (string)($data['slug'] ?? '');
        if ($slug === '') {
            $errors['slug'] = 'Slug is required';
        } elseif (!preg_match('/^[a-z0-9\/_-]+$/', $slug)) {
            $errors['slug'] = 'Slug must contain lowercase letters, numbers, dashes, underscores or slashes only';
        }

        $title = $data['metaTitle'] ?? null;
        if (
            $title !== null
            && $title !== ''
            && mb_strlen((string) $title) > self::TITLE_MAX_LENGTH
        ) {
            $errors['metaTitle'] = 'Meta title exceeds ' . self::TITLE_MAX_LENGTH . ' characters';
        }

        $description = $data['metaDescription'] ?? null;
        if (
            $description !== null
            && $description !== ''
            && mb_strlen((string) $description) > self::DESCRIPTION_MAX_LENGTH
        ) {
            $errors['metaDescription'] = 'Meta description exceeds ' . self::DESCRIPTION_MAX_LENGTH . ' characters';
        }

        $canonical = $data['canonicalUrl'] ?? null;
        if ($canonical !== null && $canonical !== '' && filter_var($canonical, FILTER_VALIDATE_URL) === false) {
            $errors['canonicalUrl'] = 'Invalid URL';
        }

        $domain = $data['domain'] ?? null;
        if ($domain !== null && $domain !== '' && !preg_match('/^[a-z0-9.-]+$/', (string) $domain)) {
            $errors['domain'] = 'Invalid domain';
        }

        $favicon = $data['faviconPath'] ?? null;
        if ($favicon !== null && $favicon !== '' && mb_strlen((string) $favicon) > 255) {
            $errors['faviconPath'] = 'Favicon path too long';
        }

        return $errors;
    }
}
