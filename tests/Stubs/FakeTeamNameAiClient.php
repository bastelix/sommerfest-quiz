<?php

declare(strict_types=1);

namespace Tests\Stubs;

use App\Service\RagChat\HttpChatResponder;
use App\Service\TeamNameAiClient;

use const JSON_THROW_ON_ERROR;
use function array_shift;
use function count;
use function json_encode;
use function trim;

/**
 * In-memory fake for the team name AI client used in tests.
 */
final class FakeTeamNameAiClient extends TeamNameAiClient
{
    /**
     * @var list<list<string>>
     */
    private array $batches;

    /**
     * @var list<array{count:int,domains:array<int,string>,tones:array<int,string>,locale:string}>
     */
    private array $calls = [];

    /**
     * @param list<list<string>> $batches
     */
    public function __construct(array $batches)
    {
        $this->batches = $batches;

        parent::__construct(
            new class () extends HttpChatResponder {
                public function __construct()
                {
                }

                public function respond(array $messages, array $context): string
                {
                    return json_encode(['names' => []], JSON_THROW_ON_ERROR);
                }
            }
        );
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return list<string>
     */
    public function fetchSuggestions(int $count, array $domains, array $tones, string $locale): array
    {
        $this->calls[] = [
            'count' => $count,
            'domains' => $domains,
            'tones' => $tones,
            'locale' => $locale,
        ];

        if ($this->batches === []) {
            return [];
        }

        $batch = array_shift($this->batches);

        $result = [];
        $seen = [];

        foreach ($batch as $candidate) {
            $normalized = trim($candidate);
            if ($normalized === '') {
                continue;
            }
            if (isset($seen[$normalized])) {
                continue;
            }

            $result[] = $normalized;
            $seen[$normalized] = true;

            if (count($result) >= $count) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return list<array{count:int,domains:array<int,string>,tones:array<int,string>,locale:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
