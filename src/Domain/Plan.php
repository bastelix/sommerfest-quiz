<?php

declare(strict_types=1);

namespace App\Domain;

final class Plan
{
    public const STARTER = 'starter';
    public const STANDARD = 'standard';
    public const PROFESSIONAL = 'professional';

    public const ALL = [
        self::STARTER,
        self::STANDARD,
        self::PROFESSIONAL,
    ];

    /**
     * @var array<string, array{maxEvents: int|null, maxTeamsPerEvent: int|null, maxCatalogsPerEvent: int|null, maxQuestionsPerCatalog: int|null}>
     */
    public const LIMITS = [
        self::STARTER => [
            'maxEvents' => 1,
            'maxTeamsPerEvent' => 5,
            'maxCatalogsPerEvent' => 5,
            'maxQuestionsPerCatalog' => 5,
        ],
        self::STANDARD => [
            'maxEvents' => 3,
            'maxTeamsPerEvent' => 10,
            'maxCatalogsPerEvent' => 10,
            'maxQuestionsPerCatalog' => 10,
        ],
        self::PROFESSIONAL => [
            'maxEvents' => 20,
            'maxTeamsPerEvent' => 100,
            'maxCatalogsPerEvent' => 50,
            'maxQuestionsPerCatalog' => 50,
        ],
    ];

    public static function limits(string $plan): array
    {
        return self::LIMITS[$plan] ?? [];
    }

    public static function isValid(string $plan): bool
    {
        return in_array($plan, self::ALL, true);
    }
}
