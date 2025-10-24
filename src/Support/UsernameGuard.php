<?php

declare(strict_types=1);

namespace App\Support;

use function array_filter;
use function array_map;
use function array_values;
use function dirname;
use function is_array;
use function is_string;
use function mb_strtolower;
use function preg_match;
use function trim;
use function is_file;

/**
 * Validates usernames against a configurable blocklist.
 */
final class UsernameGuard
{
    /**
     * @var list<string>
     */
    private array $blockedUsernames;

    /**
     * @var list<string>
     */
    private array $blockedPatterns;

    /**
     * @param array{usernames?:array<int,string>,patterns?:array<int,string>} $config
     */
    public function __construct(array $config)
    {
        $usernames = $config['usernames'] ?? [];
        $patterns = $config['patterns'] ?? [];

        $this->blockedUsernames = array_values(array_filter(array_map(
            static function ($value): ?string {
                if (!is_string($value)) {
                    return null;
                }

                $value = trim($value);
                if ($value === '') {
                    return null;
                }

                return mb_strtolower($value);
            },
            is_array($usernames) ? $usernames : []
        )));

        $this->blockedPatterns = array_values(array_filter(array_map(
            static function ($value): ?string {
                if (!is_string($value)) {
                    return null;
                }

                $value = trim($value);
                return $value === '' ? null : $value;
            },
            is_array($patterns) ? $patterns : []
        )));
    }

    public static function fromConfigFile(?string $path = null): self
    {
        $path = $path ?? dirname(__DIR__, 2) . '/config/blocked_usernames.php';
        $config = [];
        if (is_string($path) && is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }

        return new self($config);
    }

    public function assertAllowed(string $username): void
    {
        $normalized = mb_strtolower(trim($username));
        if ($normalized === '') {
            return;
        }

        foreach ($this->blockedUsernames as $blocked) {
            if ($normalized === $blocked) {
                throw UsernameBlockedException::forExactMatch($username);
            }
        }

        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                throw UsernameBlockedException::forPatternMatch($username);
            }
        }
    }
}
