<?php

declare(strict_types=1);

namespace App\Domain;

enum Plan: string
{
    case STARTER = 'starter';
    case STANDARD = 'standard';
    case PROFESSIONAL = 'professional';

    /**
     * @return array<string,int|null>
     */
    public function limits(): array {
        return match ($this) {
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
        };
    }
}
