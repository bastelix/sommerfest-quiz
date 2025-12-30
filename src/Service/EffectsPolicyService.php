<?php

declare(strict_types=1);

namespace App\Service;

class EffectsPolicyService
{
    /** @var array<string, array{label: string, description: string}> */
    private const PROFILES = [
        'calserver.professional' => [
            'label' => 'Ruhig & sachlich',
            'description' => 'Für informationslastige, professionelle Seiten',
        ],
        'quizrace.calm' => [
            'label' => 'Dezent lebendig',
            'description' => 'Sanfte Übergänge ohne Ablenkung',
        ],
        'quizrace.marketing' => [
            'label' => 'Lebendig & aufmerksamkeitsstark',
            'description' => 'Marketing-orientiert, dynamisch',
        ],
    ];

    /** @var list<string> */
    private const SLIDER_PROFILES = ['static', 'calm', 'marketing'];

    /** @var array{effectsProfile: string, sliderProfile: string} */
    private const DEFAULTS = [
        'effectsProfile' => 'calserver.professional',
        'sliderProfile' => 'static',
    ];

    public function __construct(private ConfigService $configService)
    {
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function getProfiles(): array
    {
        return self::PROFILES;
    }

    /**
     * @return array{effectsProfile: string, sliderProfile: string}
     */
    public function getDefaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * @return array{effectsProfile: string, sliderProfile: string}
     */
    public function getEffectsForNamespace(string $namespace): array
    {
        $config = $this->configService->getConfigForEvent($namespace);
        $effectsProfile = $this->validateProfile($config['effectsProfile'] ?? null);
        $sliderProfile = $this->validateSlider($config['sliderProfile'] ?? null);

        if ($effectsProfile === null) {
            $effectsProfile = self::DEFAULTS['effectsProfile'];
        }

        if ($sliderProfile === null) {
            $sliderProfile = $this->getSuggestedSliderProfile($effectsProfile);
        }

        return [
            'effectsProfile' => $effectsProfile,
            'sliderProfile' => $sliderProfile,
        ];
    }

    /**
     * @param array{effectsProfile?: string|null, sliderProfile?: string|null} $incoming
     * @return array{effectsProfile: string, sliderProfile: string}
     */
    public function normalizeIncoming(array $incoming): array
    {
        $profile = $this->validateProfile($incoming['effectsProfile'] ?? null)
            ?? self::DEFAULTS['effectsProfile'];

        $slider = $this->validateSlider($incoming['sliderProfile'] ?? null)
            ?? $this->getSuggestedSliderProfile($profile);

        return [
            'effectsProfile' => $profile,
            'sliderProfile' => $slider,
        ];
    }

    /**
     * @param array{effectsProfile: string, sliderProfile: string} $effects
     */
    public function persist(string $namespace, array $effects): void
    {
        $payload = $this->normalizeIncoming($effects);
        $this->configService->ensureConfigForEvent($namespace);
        $this->configService->saveConfig([
            'event_uid' => $namespace,
            'effectsProfile' => $payload['effectsProfile'],
            'sliderProfile' => $payload['sliderProfile'],
        ]);
    }

    public function hasSliderBlocks(): bool
    {
        return true;
    }

    public function getSuggestedSliderProfile(string $effectsProfile): string
    {
        if ($effectsProfile === 'quizrace.marketing') {
            return 'marketing';
        }
        if ($effectsProfile === 'quizrace.calm') {
            return 'calm';
        }

        return self::DEFAULTS['sliderProfile'];
    }

    private function validateProfile(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return array_key_exists($normalized, self::PROFILES) ? $normalized : null;
    }

    private function validateSlider(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return in_array($normalized, self::SLIDER_PROFILES, true) ? $normalized : null;
    }
}
