<?php

declare(strict_types=1);

namespace App\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Central allocator for curated team names with reservation support.
 */
class TeamNameService
{
    private PDO $pdo;

    /**
     * Normalized adjective lists grouped by tonality.
     *
     * @var array<string, array<int, string>>
     */
    private array $adjectiveCategories = [];

    /**
     * Normalized noun lists grouped by domain.
     *
     * @var array<string, array<int, string>>
     */
    private array $nounCategories = [];

    private int $lexiconVersion = 1;

    private int $reservationTtlSeconds;

    /**
     * Cache of computed name combinations per filter set.
     *
     * @var array<string, array{adjectives: array<int, string>, nouns: array<int, string>, names: array<int, string>, total: int}>
     */
    private array $nameCache = [];

    public function __construct(PDO $pdo, string $lexiconPath, int $reservationTtlSeconds = 600)
    {
        $this->pdo = $pdo;
        $this->reservationTtlSeconds = max(60, $reservationTtlSeconds);
        $this->loadLexicon($lexiconPath);
    }

    public function getLexiconVersion(): int
    {
        return $this->lexiconVersion;
    }

    public function getTotalCombinations(): int
    {
        $adjectives = $this->fallbackWords($this->adjectiveCategories);
        $nouns = $this->fallbackWords($this->nounCategories);

        return count($adjectives) * count($nouns);
    }

    /**
     * Reserve a name for the given event.
     *
     * @return array{
     *     name: string,
     *     token: string,
     *     expires_at: string,
     *     lexicon_version: int,
     *     total: int,
     *     remaining: int,
     *     fallback: bool
     * }
     */
    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    public function reserve(string $eventId, array $domains = [], array $tones = []): array
    {
        if ($eventId === '') {
            throw new InvalidArgumentException('eventId must not be empty');
        }

        $this->releaseExpiredReservations($eventId);

        $selection = $this->getNameSelection($domains, $tones);
        $names = $selection['names'];
        $totalNames = count($names);
        $totalCombinations = $selection['total'];
        if ($totalNames === 0) {
            return $this->reserveFallback($eventId, $totalCombinations);
        }

        $startIndex = $this->randomStartIndex($totalNames);

        for ($offset = 0; $offset < $totalNames; $offset++) {
            $index = ($startIndex + $offset) % $totalNames;
            $name = $names[$index];
            $token = bin2hex(random_bytes(16));
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO team_names (event_id, name, lexicon_version, reservation_token) VALUES (?,?,?,?)'
                );
                $stmt->execute([$eventId, $name, $this->lexiconVersion, $token]);

                return $this->formatReservationResponse($eventId, $name, $token, false, $totalCombinations);
            } catch (PDOException $exception) {
                if ($this->isUniqueViolation($exception)) {
                    continue;
                }
                throw $exception;
            }
        }

        return $this->reserveFallback($eventId, $totalCombinations);
    }

    /**
     * Reserve multiple names for the given event in a single transaction.
     *
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return array<int, array{
     *     name: string,
     *     token: string,
     *     expires_at: string,
     *     lexicon_version: int,
     *     total: int,
     *     remaining: int,
     *     fallback: bool
     * }>
     */
    public function reserveBatch(string $eventId, int $count, array $domains = [], array $tones = []): array
    {
        if ($eventId === '') {
            throw new InvalidArgumentException('eventId must not be empty');
        }

        $count = max(1, min($count, 10));

        $this->releaseExpiredReservations($eventId);

        $selection = $this->getNameSelection($domains, $tones);
        $names = $selection['names'];
        $totalCombinations = $selection['total'];

        if ($names === []) {
            return [$this->reserveFallback($eventId, $totalCombinations)];
        }

        $totalNames = count($names);
        $startIndex = $this->randomStartIndex($totalNames);
        $orderedNames = array_merge(array_slice($names, $startIndex), array_slice($names, 0, $startIndex));

        $reservations = [];

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO team_names (event_id, name, lexicon_version, reservation_token) VALUES (?,?,?,?)'
            );

            foreach ($orderedNames as $name) {
                if (count($reservations) >= $count) {
                    break;
                }

                $token = bin2hex(random_bytes(16));

                try {
                    $stmt->execute([$eventId, $name, $this->lexiconVersion, $token]);
                    $reservations[] = $this->formatReservationResponse($eventId, $name, $token, false, $totalCombinations);
                } catch (PDOException $exception) {
                    if ($this->isUniqueViolation($exception)) {
                        continue;
                    }

                    throw $exception;
                } finally {
                    $stmt->closeCursor();
                }
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        if ($reservations === []) {
            return [$this->reserveFallback($eventId, $totalCombinations)];
        }

        return $reservations;
    }

    /**
     * Confirm usage of a reserved name.
     *
     * @return array{name: string, fallback: bool}|null
     */
    public function confirm(string $eventId, string $token, ?string $expectedName = null): ?array
    {
        if ($eventId === '' || $token === '') {
            throw new InvalidArgumentException('eventId and token are required');
        }

        $this->releaseExpiredReservations($eventId);

        $stmt = $this->pdo->prepare(
            'SELECT id, name, fallback, assigned_at FROM team_names '
            . 'WHERE event_id = ? AND reservation_token = ? AND released_at IS NULL'
        );
        $stmt->execute([$eventId, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $name = (string) $row['name'];
        if ($expectedName !== null && $this->normalize($expectedName) !== $this->normalize($name)) {
            return null;
        }

        if ($row['assigned_at'] === null) {
            $update = $this->pdo->prepare('UPDATE team_names SET assigned_at = CURRENT_TIMESTAMP WHERE id = ?');
            $update->execute([(int) $row['id']]);
        }

        return [
            'name' => $name,
            'fallback' => (bool) $row['fallback'],
        ];
    }

    public function release(string $eventId, string $token): bool
    {
        if ($eventId === '' || $token === '') {
            throw new InvalidArgumentException('eventId and token are required');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE team_names SET released_at = CURRENT_TIMESTAMP '
            . 'WHERE event_id = ? AND reservation_token = ? AND released_at IS NULL'
        );
        $stmt->execute([$eventId, $token]);
        return $stmt->rowCount() > 0;
    }

    public function releaseByName(string $eventId, string $name): void
    {
        if ($eventId === '' || $name === '') {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE team_names SET released_at = CURRENT_TIMESTAMP '
            . 'WHERE event_id = ? AND name = ? AND released_at IS NULL'
        );
        $stmt->execute([$eventId, $name]);
    }

    private function reserveFallback(string $eventId, int $totalCombinations): array
    {
        $token = bin2hex(random_bytes(16));
        $name = 'Gast-' . strtoupper(substr($token, 0, 5));
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO team_names (event_id, name, lexicon_version, reservation_token, fallback) '
                . 'VALUES (?,?,?,?,TRUE)'
            );
            $stmt->execute([$eventId, $name, $this->lexiconVersion, $token]);
        } catch (PDOException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return $this->reserveFallback($eventId, $totalCombinations);
            }
            throw $exception;
        }

        $response = $this->formatReservationResponse($eventId, $name, $token, true, $totalCombinations);
        $response['remaining'] = 0;
        return $response;
    }

    /**
     * @return array{
     *     name: string,
     *     token: string,
     *     expires_at: string,
     *     lexicon_version: int,
     *     total: int,
     *     remaining: int,
     *     fallback: bool
     * }
     */
    private function formatReservationResponse(
        string $eventId,
        string $name,
        string $token,
        bool $fallback,
        int $totalCombinations
    ): array
    {
        $expiresAt = $this->now()->add(new DateInterval('PT' . $this->reservationTtlSeconds . 'S'));
        $active = $this->countActiveAssignments($eventId);

        return [
            'name' => $name,
            'token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'lexicon_version' => $this->lexiconVersion,
            'total' => $totalCombinations,
            'remaining' => max(0, $totalCombinations - $active),
            'fallback' => $fallback,
        ];
    }

    private function countActiveAssignments(string $eventId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM team_names WHERE event_id = ? AND released_at IS NULL');
        $stmt->execute([$eventId]);
        return (int) $stmt->fetchColumn();
    }

    private function releaseExpiredReservations(string $eventId): void
    {
        $threshold = $this->now()->sub(new DateInterval('PT' . $this->reservationTtlSeconds . 'S'));
        $stmt = $this->pdo->prepare(
            'UPDATE team_names SET released_at = CURRENT_TIMESTAMP '
            . 'WHERE event_id = ? AND released_at IS NULL AND assigned_at IS NULL AND reserved_at <= ?'
        );
        $stmt->execute([$eventId, $threshold->format('Y-m-d H:i:sP')]);
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return array{
     *     adjectives: array<int, string>,
     *     nouns: array<int, string>,
     *     names: array<int, string>,
     *     total: int
     * }
     */
    private function getNameSelection(array $domains, array $tones): array
    {
        $normalizedDomains = $this->normalizeFilterValues($domains);
        $normalizedTones = $this->normalizeFilterValues($tones);
        $cacheKey = $this->buildCacheKey($normalizedDomains, $normalizedTones);
        if (isset($this->nameCache[$cacheKey])) {
            return $this->nameCache[$cacheKey];
        }

        $adjectives = $this->selectWords($this->adjectiveCategories, $normalizedTones);
        $nouns = $this->selectWords($this->nounCategories, $normalizedDomains);

        $names = [];
        $total = 0;

        if ($adjectives !== [] && $nouns !== []) {
            foreach ($adjectives as $adj) {
                foreach ($nouns as $noun) {
                    $names[] = trim($adj . ' ' . $noun);
                }
            }
            $total = count($adjectives) * count($nouns);
        }

        $selection = [
            'adjectives' => $adjectives,
            'nouns' => $nouns,
            'names' => $names,
            'total' => $total,
        ];

        $this->nameCache[$cacheKey] = $selection;

        return $selection;
    }

    /**
     * @param array<int, string> $filters
     *
     * @return array<int, string>
     */
    private function normalizeFilterValues(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $filter) {
            if (is_array($filter) || is_object($filter)) {
                continue;
            }
            $value = $this->normalize((string) $filter);
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return $normalized;
    }

    /**
     * @param array<string, array<int, string>> $categories
     * @param array<int, string> $filters
     *
     * @return array<int, string>
     */
    private function selectWords(array $categories, array $filters): array
    {
        $selectedFilters = $filters === [] ? ['default'] : $filters;

        $words = [];
        foreach ($selectedFilters as $filter) {
            if (!isset($categories[$filter])) {
                continue;
            }
            $words = array_merge($words, $categories[$filter]);
        }

        $words = array_values(array_unique($words));
        sort($words, SORT_NATURAL | SORT_FLAG_CASE);

        if ($words === []) {
            $words = $this->fallbackWords($categories);
        }

        return $words;
    }

    /**
     * @param array<string, array<int, string>> $categories
     *
     * @return array<int, string>
     */
    private function fallbackWords(array $categories): array
    {
        $nonEmpty = [];
        foreach ($categories as $key => $words) {
            if (!is_array($words) || $words === []) {
                continue;
            }
            $nonEmpty[$key] = $words;
        }

        if (isset($nonEmpty['default'])) {
            return $nonEmpty['default'];
        }

        return $this->collectAllWords($nonEmpty);
    }

    /**
     * @param array<string, array<int, string>> $categories
     *
     * @return array<int, string>
     */
    private function collectAllWords(array $categories): array
    {
        $merged = [];
        foreach ($categories as $words) {
            if (!is_array($words) || $words === []) {
                continue;
            }
            $merged = array_merge($merged, $words);
        }

        $merged = array_values(array_unique($merged));
        sort($merged, SORT_NATURAL | SORT_FLAG_CASE);

        return $merged;
    }

    /**
     * @param array<mixed> $domains
     * @param array<mixed> $tones
     */
    private function buildCacheKey(array $domains, array $tones): string
    {
        return sha1(implode('|', $domains) . '#' . implode('|', $tones));
    }

    /**
     * @param mixed $section
     *
     * @return array<string, array<int, string>>
     */
    private function normalizeLexiconSection($section): array
    {
        if (!is_array($section)) {
            return [];
        }

        if ($section === []) {
            return [];
        }

        if (!$this->isAssociativeArray($section)) {
            $words = $this->normalizeWordList($section);
            return $words === [] ? [] : ['default' => $words];
        }

        $normalized = [];
        foreach ($section as $key => $words) {
            if (!is_string($key)) {
                continue;
            }
            $categoryKey = $this->normalizeKey($key);
            if ($categoryKey === '') {
                continue;
            }
            $normalized[$categoryKey] = $this->normalizeWordList($words);
        }

        if ($normalized === []) {
            return [];
        }

        if (!isset($normalized['default']) || $normalized['default'] === []) {
            $merged = $this->collectAllWords($normalized);
            if ($merged !== []) {
                $normalized['default'] = $merged;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param mixed $words
     *
     * @return array<int, string>
     */
    private function normalizeWordList($words): array
    {
        if (!is_array($words)) {
            return [];
        }

        $normalized = [];
        foreach ($words as $word) {
            if (is_array($word) || is_object($word)) {
                continue;
            }
            $value = trim((string) $word);
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return $normalized;
    }

    private function isAssociativeArray(array $input): bool
    {
        return array_keys($input) !== range(0, count($input) - 1);
    }

    private function normalizeKey(string $key): string
    {
        return $this->normalize($key);
    }

    private function loadLexicon(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Team name lexicon not found at %s', $path));
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read team name lexicon');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid team name lexicon');
        }

        $adjectives = $this->normalizeLexiconSection($data['adjectives'] ?? []);
        $nouns = $this->normalizeLexiconSection($data['nouns'] ?? []);

        if ($adjectives === [] || $nouns === []) {
            throw new RuntimeException('Team name lexicon requires adjectives and nouns');
        }

        $this->adjectiveCategories = $adjectives;
        $this->nounCategories = $nouns;
        $this->nameCache = [];
        $this->lexiconVersion = is_int($data['version'] ?? null) ? (int) $data['version'] : 1;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    protected function randomStartIndex(int $total): int
    {
        if ($total <= 1) {
            return 0;
        }

        try {
            return random_int(0, $total - 1);
        } catch (Throwable $exception) {
            return 0;
        }
    }

    private function isUniqueViolation(PDOException $exception): bool
    {
        $code = $exception->getCode();
        return $code === '23505' || $code === '23000';
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
