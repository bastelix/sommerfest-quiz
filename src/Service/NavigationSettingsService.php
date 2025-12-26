<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ProjectSettingsRepository;
use RuntimeException;

final class NavigationSettingsService
{
    public const MODE_TEXT = 'text';
    public const MODE_IMAGE = 'image';

    private const MAX_ALT_LENGTH = 200;

    private ProjectSettingsRepository $repository;

    public function __construct(?ProjectSettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new ProjectSettingsRepository();
    }

    /**
     * @return array{namespace: string, logo_mode: string, logo_image: ?string, logo_alt: ?string}
     */
    public function getSettings(string $namespace): array
    {
        $normalized = $this->normalizeNamespace($namespace);
        $defaults = [
            'namespace' => $normalized,
            'logo_mode' => self::MODE_TEXT,
            'logo_image' => null,
            'logo_alt' => null,
        ];

        if (!$this->repository->hasTable()) {
            return $defaults;
        }

        $row = $this->repository->fetch($normalized);
        if ($row === null && $normalized !== PageService::DEFAULT_NAMESPACE) {
            $row = $this->repository->fetch(PageService::DEFAULT_NAMESPACE);
        }

        if ($row === null) {
            return $defaults;
        }

        $mode = $this->normalizeMode($row['navigation_logo_mode'] ?? null);
        $image = isset($row['navigation_logo_image']) ? trim((string) $row['navigation_logo_image']) : '';
        $alt = isset($row['navigation_logo_alt']) ? trim((string) $row['navigation_logo_alt']) : '';

        return [
            'namespace' => $normalized,
            'logo_mode' => $mode,
            'logo_image' => $image !== '' ? $image : null,
            'logo_alt' => $alt !== '' ? $alt : null,
        ];
    }

    /**
     * @return array{namespace: string, logo_mode: string, logo_image: ?string, logo_alt: ?string}
     */
    public function saveSettings(
        string $namespace,
        string $logoMode,
        ?string $logoImage,
        ?string $logoAlt
    ): array {
        $normalized = $this->normalizeNamespace($namespace);
        $mode = $this->normalizeMode($logoMode);
        $this->assertTableExists();

        $normalizedImage = $logoImage !== null ? trim($logoImage) : '';
        $normalizedAlt = $logoAlt !== null ? trim($logoAlt) : '';

        if ($mode === self::MODE_IMAGE && $normalizedImage === '') {
            throw new RuntimeException('Logo-Bild wird benötigt.');
        }

        if ($normalizedAlt !== '' && mb_strlen($normalizedAlt) > self::MAX_ALT_LENGTH) {
            throw new RuntimeException('Alternativtext ist zu lang.');
        }

        $this->repository->upsertNavigationSettings(
            $normalized,
            $mode,
            $normalizedImage !== '' ? $normalizedImage : null,
            $normalizedAlt !== '' ? $normalizedAlt : null
        );

        return $this->getSettings($normalized);
    }

    private function normalizeMode(mixed $value): string
    {
        $candidate = is_string($value) ? strtolower(trim($value)) : '';
        if (in_array($candidate, [self::MODE_TEXT, self::MODE_IMAGE], true)) {
            return $candidate;
        }

        return self::MODE_TEXT;
    }

    private function normalizeNamespace(string $namespace): string
    {
        $validator = new NamespaceValidator();

        return $validator->normalize($namespace);
    }

    private function assertTableExists(): void
    {
        if (!$this->repository->hasTable()) {
            throw new RuntimeException('Project settings table is not available.');
        }
    }
}
