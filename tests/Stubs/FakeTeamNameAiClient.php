<?php

declare(strict_types=1);

namespace Tests\Stubs;

use App\Service\RagChat\HttpChatResponder;
use App\Service\TeamNameAiClient;

use function array_keys;
use function array_shift;
use function array_values;
use function count;
use function in_array;
use function json_encode;
use function max;
use function min;
use function trim;

use const JSON_THROW_ON_ERROR;

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
     * @var list<array{count:int,domains:array<int,string>,tones:array<int,string>,locale:string,existing_names:array<int,string>}>
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
     * @param array<int, string> $existingNames
     *
     * @return list<string>
     */
    public function fetchSuggestions(int $count, array $domains, array $tones, string $locale, array $existingNames): array
    {
        $count = max(1, min(self::MAX_FETCH_COUNT, $count));
        $normalizedExisting = [];
        foreach ($existingNames as $name) {
            $candidate = trim((string) $name);
            if ($candidate === '') {
                continue;
            }
            if (!in_array($candidate, $normalizedExisting, true)) {
                $normalizedExisting[] = $candidate;
            }
            if (count($normalizedExisting) >= self::EXISTING_NAMES_LIMIT) {
                break;
            }
        }
        $blocked = [];
        foreach ($normalizedExisting as $candidate) {
            $blocked[$candidate] = true;
        }
        $this->calls[] = [
            'count' => $count,
            'domains' => $domains,
            'tones' => $tones,
            'locale' => $locale,
            'existing_names' => $normalizedExisting,
        ];

        if ($this->batches === []) {
            $this->recordFailure('Fake AI client has no configured batches.');

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
            if (isset($blocked[$normalized])) {
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

        if ($result === []) {
            $this->recordFailure('Fake AI client returned no results.');
        } else {
            $this->recordSuccess();
        }

        return $result;
    }

    /**
     * @return list<array{count:int,domains:array<int,string>,tones:array<int,string>,locale:string,existing_names:array<int,string>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
