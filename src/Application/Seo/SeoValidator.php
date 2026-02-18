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

    /** @var array<string,string> Human-readable German labels for SEO fields. */
    private const FIELD_LABELS = [
        'metaTitle' => 'Meta Title',
        'metaDescription' => 'Meta Description',
        'slug' => 'Slug',
        'canonicalUrl' => 'Canonical URL',
        'domain' => 'Domain',
        'faviconPath' => 'Favicon-Pfad',
    ];

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
            $errors['slug'] = self::FIELD_LABELS['slug'] . ': Pflichtfeld – bitte einen Slug eingeben.';
        } elseif (!preg_match('/^[a-z0-9\/_-]+$/', $slug)) {
            $errors['slug'] = self::FIELD_LABELS['slug'] . ': Nur Kleinbuchstaben, Ziffern, Bindestriche, Unterstriche und Schrägstriche erlaubt.';
        }

        $title = $data['metaTitle'] ?? null;
        if (
            $title !== null
            && $title !== ''
            && mb_strlen((string) $title) > self::TITLE_MAX_LENGTH
        ) {
            $len = mb_strlen((string) $title);
            $errors['metaTitle'] = self::FIELD_LABELS['metaTitle'] . ': Text zu lang – ' . $len . '/' . self::TITLE_MAX_LENGTH . ' Zeichen.';
        }

        $description = $data['metaDescription'] ?? null;
        if (
            $description !== null
            && $description !== ''
            && mb_strlen((string) $description) > self::DESCRIPTION_MAX_LENGTH
        ) {
            $len = mb_strlen((string) $description);
            $errors['metaDescription'] = self::FIELD_LABELS['metaDescription'] . ': Text zu lang – ' . $len . '/' . self::DESCRIPTION_MAX_LENGTH . ' Zeichen.';
        }

        $canonical = $data['canonicalUrl'] ?? null;
        if ($canonical !== null && $canonical !== '' && filter_var($canonical, FILTER_VALIDATE_URL) === false) {
            $errors['canonicalUrl'] = self::FIELD_LABELS['canonicalUrl'] . ': Ungültige URL.';
        }

        $domain = $data['domain'] ?? null;
        if ($domain !== null && $domain !== '' && !preg_match('/^[a-z0-9.-]+$/', (string) $domain)) {
            $errors['domain'] = self::FIELD_LABELS['domain'] . ': Ungültiger Domainname.';
        }

        $favicon = $data['faviconPath'] ?? null;
        if ($favicon !== null && $favicon !== '' && mb_strlen((string) $favicon) > 255) {
            $len = mb_strlen((string) $favicon);
            $errors['faviconPath'] = self::FIELD_LABELS['faviconPath'] . ': Pfad zu lang – ' . $len . '/255 Zeichen.';
        }

        return $errors;
    }
}
