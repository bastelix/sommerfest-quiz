<?php

declare(strict_types=1);

namespace App\Support\Censor;

/**
 * Lightweight abstraction to detect blocked words in usernames.
 */
interface UsernameCensor
{
    /**
     * @param list<string> $terms
     */
    public function addFromArray(array $terms): void;

    /**
     * @return array{matched:list<string>}
     */
    public function censorString(string $input): array;
}
