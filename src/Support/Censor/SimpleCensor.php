<?php

declare(strict_types=1);

namespace App\Support\Censor;

use function array_unique;
use function array_values;
use function mb_strtolower;
use function preg_match;
use function preg_quote;
use function trim;

/**
 * Minimal fallback implementation when the banbuilder dependency is not available.
 */
final class SimpleCensor implements UsernameCensor
{
    /**
     * @var list<string>
     */
    private array $terms = [];

    /**
     * @param list<string> $terms
     */
    public function addFromArray(array $terms): void
    {
        foreach ($terms as $term) {
            $normalized = mb_strtolower(trim($term));
            if ($normalized === '') {
                continue;
            }

            $this->terms[] = $normalized;
        }
    }

    /**
     * @return array{matched:list<string>}
     */
    public function censorString(string $input): array
    {
        $normalized = mb_strtolower($input);
        $matches = [];

        foreach ($this->terms as $term) {
            $pattern = '/(?:^|[^a-z0-9])' . preg_quote($term, '/') . '(?:$|[^a-z0-9])/iu';
            if (preg_match($pattern, $normalized) === 1) {
                $matches[] = $term;
            }
        }

        return [
            'matched' => array_values(array_unique($matches)),
        ];
    }
}
