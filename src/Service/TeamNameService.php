<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\TeamNameAiClient;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

use function array_merge;
use function array_slice;
use function array_splice;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_object;
use function max;
use function min;
use function sha1;
use function trim;

/**
 * Central allocator for curated team names with reservation support.
 */
class TeamNameService
{
    private const AI_MAX_ATTEMPTS = 3;
    private const DEFAULT_LOCALE = 'de';

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

    private ?TeamNameAiClient $aiClient;

    private bool $aiEnabled;

    private string $defaultLocale;

    /**
     * Cached AI generated names keyed by event and filter combination.
     *
     * @var array<string, array<int, string>>
     */
    private array $aiNameCache = [];

    public function __construct(
        PDO $pdo,
        string $lexiconPath,
        int $reservationTtlSeconds = 600,
        ?TeamNameAiClient $aiClient = null,
        bool $enableAi = true,
        ?string $defaultLocale = null
    ) {
        $this->pdo = $pdo;
        $this->reservationTtlSeconds = max(60, $reservationTtlSeconds);
        $this->aiClient = $aiClient;
        $this->aiEnabled = $enableAi && $aiClient !== null;
        $locale = trim((string) ($defaultLocale ?? ''));
        $this->defaultLocale = $locale === '' ? self::DEFAULT_LOCALE : $locale;
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
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     * @param int $randomNameBuffer
     * @param string|null $locale
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
    public function reserve(
        string $eventId,
        array $domains = [],
        array $tones = [],
        int $randomNameBuffer = 0,
        ?string $locale = null
    ): array
    {
        if ($eventId === '') {
            throw new InvalidArgumentException('eventId must not be empty');
        }

        $this->releaseExpiredReservations($eventId);

        $aiCandidates = $this->consumeAiSuggestions($eventId, 1, $domains, $tones, $randomNameBuffer, $locale);
        $selection = $this->getNameSelection($domains, $tones);
        $names = $selection['names'];
        $totalCombinations = $selection['total'];
        $orderedNames = [];
        if ($names !== []) {
            $totalNames = count($names);
            $startIndex = $this->randomStartIndex($totalNames);
            $orderedNames = array_merge(
                array_slice($names, $startIndex),
                array_slice($names, 0, $startIndex)
            );
        }

        $candidates = array_merge($aiCandidates, $orderedNames);
        $reservations = $this->reserveCandidates($eventId, $candidates, 1, $totalCombinations);
        if ($reservations !== []) {
            return $reservations[0];
        }

        return $this->reserveFallback($eventId, $totalCombinations);
    }

    /**
     * Reserve multiple names for the given event in a single transaction.
     *
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     * @param int $randomNameBuffer
     * @param string|null $locale
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
    public function reserveBatch(
        string $eventId,
        int $count,
        array $domains = [],
        array $tones = [],
        int $randomNameBuffer = 0,
        ?string $locale = null
    ): array
    {
        if ($eventId === '') {
            throw new InvalidArgumentException('eventId must not be empty');
        }

        $count = max(1, min($count, 10));

        $this->releaseExpiredReservations($eventId);

        $aiCandidates = $this->consumeAiSuggestions($eventId, $count, $domains, $tones, $randomNameBuffer, $locale);
        $selection = $this->getNameSelection($domains, $tones);
        $names = $selection['names'];
        $totalCombinations = $selection['total'];

        $orderedNames = [];
        if ($names !== []) {
            $totalNames = count($names);
            $startIndex = $this->randomStartIndex($totalNames);
            $orderedNames = array_merge(
                array_slice($names, $startIndex),
                array_slice($names, 0, $startIndex)
            );
        }

        $candidates = array_merge($aiCandidates, $orderedNames);
        $reservations = $this->reserveCandidates($eventId, $candidates, $count, $totalCombinations);

        if ($reservations === []) {
            return [$this->reserveFallback($eventId, $totalCombinations)];
        }

        return $reservations;
    }

    /**
     * @param array<int, string> $candidates
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
    private function reserveCandidates(string $eventId, array $candidates, int $limit, int $totalCombinations): array
    {
        if ($candidates === []) {
            return [];
        }

        $reservations = [];
        $useTransaction = $limit > 1;

        if ($useTransaction) {
            $this->pdo->beginTransaction();
        }

        $stmt = null;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO team_names (event_id, name, lexicon_version, reservation_token) VALUES (?,?,?,?)'
            );

            foreach ($candidates as $name) {
                if (count($reservations) >= $limit) {
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
                    if ($stmt !== null) {
                        $stmt->closeCursor();
                    }
                }
            }

            if ($useTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($useTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return $reservations;
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return array<int, string>
     */
    private function consumeAiSuggestions(
        string $eventId,
        int $count,
        array $domains,
        array $tones,
        int $randomNameBuffer,
        ?string $locale
    ): array {
        if (!$this->canUseAi()) {
            return [];
        }

        $count = max(0, $count);
        $buffer = max(0, $randomNameBuffer);
        if ($count === 0 && $buffer === 0) {
            return [];
        }

        $normalizedDomains = $this->normalizeFilterValues($domains);
        $normalizedTones = $this->normalizeFilterValues($tones);
        $cacheKey = $this->buildAiCacheKey($eventId, $normalizedDomains, $normalizedTones);
        $promptDomains = $this->preparePromptValues($domains);
        $promptTones = $this->preparePromptValues($tones);
        $resolvedLocale = $this->resolveLocale($locale);

        $targetSize = max(1, max($count, $buffer));
        $this->fillAiCache($cacheKey, $eventId, $promptDomains, $promptTones, $resolvedLocale, $targetSize);

        $available = $this->aiNameCache[$cacheKey] ?? [];
        if ($available === []) {
            return [];
        }

        $selection = array_splice($this->aiNameCache[$cacheKey], 0, min($count, count($available)));

        if ($buffer > 0) {
            $this->fillAiCache($cacheKey, $eventId, $promptDomains, $promptTones, $resolvedLocale, $buffer);
        }

        return $selection;
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    private function fillAiCache(
        string $cacheKey,
        string $eventId,
        array $domains,
        array $tones,
        string $locale,
        int $targetSize
    ): void {
        if (!$this->canUseAi() || $targetSize <= 0) {
            return;
        }

        if (!isset($this->aiNameCache[$cacheKey])) {
            $this->aiNameCache[$cacheKey] = [];
        }

        $attempts = 0;
        while (count($this->aiNameCache[$cacheKey]) < $targetSize && $attempts < self::AI_MAX_ATTEMPTS) {
            $needed = $targetSize - count($this->aiNameCache[$cacheKey]);
            $batch = $this->aiClient->fetchSuggestions($needed, $domains, $tones, $locale);
            if ($batch === []) {
                break;
            }

            $added = false;
            foreach ($batch as $candidate) {
                $normalizedName = $this->normalize($candidate);
                if ($normalizedName === '') {
                    continue;
                }
                if ($this->isNameAlreadyInCache($cacheKey, $normalizedName)) {
                    continue;
                }
                if ($this->isNameAlreadyActive($eventId, $candidate)) {
                    continue;
                }
                $this->aiNameCache[$cacheKey][] = $candidate;
                $added = true;
                if (count($this->aiNameCache[$cacheKey]) >= $targetSize) {
                    break;
                }
            }

            if (!$added) {
                break;
            }

            $attempts++;
        }
    }

    private function canUseAi(): bool
    {
        return $this->aiEnabled && $this->aiClient !== null;
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    private function buildAiCacheKey(string $eventId, array $domains, array $tones): string
    {
        return sha1($this->normalize($eventId) . '#' . implode('|', $domains) . '#' . implode('|', $tones));
    }

    /**
     * @param array<int, string> $values
     *
     * @return array<int, string>
     */
    private function preparePromptValues(array $values): array
    {
        $prepared = [];
        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $candidate = trim((string) $value);
            if ($candidate === '') {
                continue;
            }
            if (!in_array($candidate, $prepared, true)) {
                $prepared[] = $candidate;
            }
        }

        return $prepared;
    }

    private function resolveLocale(?string $locale): string
    {
        $locale = $locale !== null ? trim($locale) : '';
        if ($locale === '') {
            return $this->defaultLocale;
        }

        return $locale;
    }

    private function isNameAlreadyInCache(string $cacheKey, string $normalizedName): bool
    {
        if (!isset($this->aiNameCache[$cacheKey])) {
            return false;
        }

        foreach ($this->aiNameCache[$cacheKey] as $existing) {
            if ($this->normalize($existing) === $normalizedName) {
                return true;
            }
        }

        return false;
    }

    private function isNameAlreadyActive(string $eventId, string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM team_names WHERE event_id = ? AND LOWER(name) = LOWER(?) '
            . 'AND released_at IS NULL LIMIT 1'
        );
        $stmt->execute([$eventId, $name]);
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $result !== false;
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
