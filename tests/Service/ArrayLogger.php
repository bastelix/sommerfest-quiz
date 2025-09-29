<?php

declare(strict_types=1);

namespace Tests\Service;

use Psr\Log\AbstractLogger;

/**
 * Simple in-memory logger for tests.
 */
class ArrayLogger extends AbstractLogger
{
    /** @var list<array{level:string,message:string}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
    }

    public function has(string $level, string $fragment): bool {
        foreach ($this->records as $r) {
            if ($r['level'] === $level && str_contains($r['message'], $fragment)) {
                return true;
            }
        }
        return false;
    }
}
