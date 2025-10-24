<?php

declare(strict_types=1);

namespace App\Support\Censor;

use function array_values;
use function class_exists;
use function is_array;

/**
 * Adapter around snipe/banbuilder when the dependency is installed.
 */
final class BanBuilderCensor implements UsernameCensor
{
    /**
     * @var object
     */
    private object $inner;

    /**
     * @param object $inner
     */
    public function __construct(object $inner)
    {
        $this->inner = $inner;
    }

    public static function isSupported(): bool
    {
        return class_exists(\Snipe\BanBuilder\CensorWords::class);
    }

    public static function create(): self
    {
        if (!self::isSupported()) {
            throw new \RuntimeException('snipe/banbuilder is not installed.');
        }

        /** @var \Snipe\BanBuilder\CensorWords $instance */
        $instance = new \Snipe\BanBuilder\CensorWords();

        return new self($instance);
    }

    /**
     * @param list<string> $terms
     */
    public function addFromArray(array $terms): void
    {
        $this->inner->addFromArray($terms);
    }

    /**
     * @return array{matched:list<string>}
     */
    public function censorString(string $input): array
    {
        $result = $this->inner->censorString($input);

        if (!is_array($result)) {
            return ['matched' => []];
        }

        $matches = $result['matched'];

        if (!is_array($matches)) {
            $matches = [];
        }

        /** @var list<string> $normalized */
        $normalized = array_values($matches);

        return ['matched' => $normalized];
    }
}
