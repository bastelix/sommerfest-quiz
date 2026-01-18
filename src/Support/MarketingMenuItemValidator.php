<?php

declare(strict_types=1);

namespace App\Support;

final class MarketingMenuItemValidator
{
    private const MAX_LABEL_LENGTH = 64;
    private const MAX_HREF_LENGTH = 2048;
    private const MAX_ICON_LENGTH = 64;
    private const MAX_LOCALE_LENGTH = 8;
    private const MAX_DETAIL_TITLE_LENGTH = 160;
    private const MAX_DETAIL_TEXT_LENGTH = 500;
    private const MAX_DETAIL_SUBLINE_LENGTH = 160;

    /** @var string[] */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];
    private const ALLOWED_LAYOUTS = ['link', 'dropdown', 'mega', 'column'];

    /**
     * @return array{array<string, mixed>, array<string, string>}
     */
    public function validatePayload(array $payload, string $basePath): array
    {
        $errors = [];

        $label = isset($payload['label']) ? trim((string) $payload['label']) : '';
        if ($label === '') {
            $errors['label'] = 'Label is required.';
        } elseif (mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            $errors['label'] = sprintf('Label must be at most %d characters.', self::MAX_LABEL_LENGTH);
        }

        $href = isset($payload['href']) ? trim((string) $payload['href']) : '';
        if ($href === '') {
            $errors['href'] = 'Href is required.';
        } elseif (mb_strlen($href) > self::MAX_HREF_LENGTH) {
            $errors['href'] = sprintf('Href must be at most %d characters.', self::MAX_HREF_LENGTH);
        } else {
            $hrefError = $this->validateHref($href, $basePath);
            if ($hrefError !== null) {
                $errors['href'] = $hrefError;
            }
        }

        $icon = isset($payload['icon']) ? trim((string) $payload['icon']) : null;
        if ($icon !== null && $icon !== '' && mb_strlen($icon) > self::MAX_ICON_LENGTH) {
            $errors['icon'] = sprintf('Icon must be at most %d characters.', self::MAX_ICON_LENGTH);
        }

        $layout = isset($payload['layout']) ? strtolower(trim((string) $payload['layout'])) : 'link';
        if (!in_array($layout, self::ALLOWED_LAYOUTS, true)) {
            $errors['layout'] = 'Layout is invalid.';
        }

        $parentId = isset($payload['parentId']) ? (int) $payload['parentId'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }

        $locale = isset($payload['locale']) ? strtolower(trim((string) $payload['locale'])) : null;
        if ($locale !== null && $locale !== '' && mb_strlen($locale) > self::MAX_LOCALE_LENGTH) {
            $errors['locale'] = sprintf('Locale must be at most %d characters.', self::MAX_LOCALE_LENGTH);
        }

        $detailTitle = isset($payload['detailTitle']) ? trim((string) $payload['detailTitle']) : null;
        if ($detailTitle !== null && $detailTitle !== '' && mb_strlen($detailTitle) > self::MAX_DETAIL_TITLE_LENGTH) {
            $errors['detailTitle'] = sprintf(
                'Detail title must be at most %d characters.',
                self::MAX_DETAIL_TITLE_LENGTH
            );
        }

        $detailText = isset($payload['detailText']) ? trim((string) $payload['detailText']) : null;
        if ($detailText !== null && $detailText !== '' && mb_strlen($detailText) > self::MAX_DETAIL_TEXT_LENGTH) {
            $errors['detailText'] = sprintf(
                'Detail text must be at most %d characters.',
                self::MAX_DETAIL_TEXT_LENGTH
            );
        }

        $detailSubline = isset($payload['detailSubline']) ? trim((string) $payload['detailSubline']) : null;
        if (
            $detailSubline !== null
            && $detailSubline !== ''
            && mb_strlen($detailSubline) > self::MAX_DETAIL_SUBLINE_LENGTH
        ) {
            $errors['detailSubline'] = sprintf(
                'Detail subline must be at most %d characters.',
                self::MAX_DETAIL_SUBLINE_LENGTH
            );
        }

        $position = null;
        if (array_key_exists('position', $payload)) {
            $position = (int) $payload['position'];
            if ($position < 0) {
                $errors['position'] = 'Position must be zero or greater.';
            }
        }

        $data = [
            'id' => isset($payload['id']) ? (int) $payload['id'] : null,
            'label' => $label,
            'href' => $href,
            'icon' => $icon !== '' ? $icon : null,
            'parentId' => $parentId,
            'layout' => $layout,
            'position' => $position,
            'isExternal' => $this->normalizeBoolean($payload['isExternal'] ?? $payload['external'] ?? false),
            'locale' => $locale !== '' ? $locale : null,
            'isActive' => $this->normalizeBoolean($payload['isActive'] ?? true),
            'isStartpage' => $this->normalizeBoolean($payload['isStartpage'] ?? false),
            'detailTitle' => $detailTitle !== '' ? $detailTitle : null,
            'detailText' => $detailText !== '' ? $detailText : null,
            'detailSubline' => $detailSubline !== '' ? $detailSubline : null,
        ];

        return [$data, $errors];
    }

    private function validateHref(string $href, string $basePath): ?string
    {
        if (str_starts_with($href, '//')) {
            return 'Protocol-relative URLs are not allowed.';
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            $scheme = strtolower($scheme);
            if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
                return 'URL scheme is not allowed.';
            }

            return null;
        }

        $firstChar = $href[0] ?? '';
        if ($firstChar === '#' || $firstChar === '?') {
            return null;
        }

        if ($firstChar !== '/') {
            return 'Link must be basePath-relative.';
        }

        if ($basePath === '') {
            return null;
        }

        if ($href === $basePath || $href === $basePath . '/') {
            return null;
        }

        if (!str_starts_with($href, $basePath . '/')) {
            return 'Link must start with the basePath.';
        }

        return null;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
