<?php

declare(strict_types=1);

namespace Tests\Stubs;

use App\Service\TeamNameWarmupDispatcher;

/**
 * Fake dispatcher that records scheduled warm-up jobs during tests.
 */
final class FakeTeamNameWarmupDispatcher extends TeamNameWarmupDispatcher
{
    /**
     * @var list<array{eventId:string,domains:array<int,string>,tones:array<int,string>,locale:?string,count:int}>
     */
    private array $calls = [];

    public function __construct()
    {
        // Intentionally bypass parent setup to avoid background processes in tests.
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    public function dispatchWarmup(
        string $eventId,
        array $domains,
        array $tones,
        ?string $locale,
        int $count
    ): void {
        $this->calls[] = [
            'eventId' => $eventId,
            'domains' => $domains,
            'tones' => $tones,
            'locale' => $locale,
            'count' => $count,
        ];
    }

    /**
     * @return list<array{eventId:string,domains:array<int,string>,tones:array<int,string>,locale:?string,count:int}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
