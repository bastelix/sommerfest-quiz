<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TeamNameAiCacheRepository;
use App\Service\TeamNameAiClient;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

use function array_map;
use function array_merge;
use function array_slice;
use function array_splice;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_object;
use function max;
use function min;
use function preg_replace;
use function sha1;
use function trim;
use const DATE_ATOM;

/**
 * Central allocator for curated team names with reservation support.
 */
class TeamNameService
{
    /**
     * Limit AI fetch retries to keep individual requests fast enough for the proxy timeout.
     */
    private const AI_MAX_ATTEMPTS = 2;
    private const DEFAULT_LOCALE = 'de';
    private const AI_LOW_WATERMARK = 10;
    private const RANDOM_NAME_STRATEGY_AI = 'ai';
    private const RANDOM_NAME_STRATEGY_LEXICON = 'lexicon';

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

    private TeamNameAiCacheRepository $aiCacheRepository;

    private ?TeamNameWarmupDispatcher $teamNameWarmupDispatcher;

    /**
     * Tracks events whose AI caches were hydrated from persistent storage.
     *
     * @var array<string, bool>
     */
    private array $aiCacheLoadedEvents = [];

    /**
     * Cached AI generated names keyed by event and filter combination.
     *
     * @var array<string, array<int, string>>
     */
    private array $aiNameCache = [];

    /**
     * Index of AI cache keys grouped by event identifier.
     *
     * @var array<string, array<int, string>>
     */
    private array $aiCacheIndex = [];

    /**
     * Metadata for cached AI suggestions keyed by cache identifier.
     *
     * @var array<string, array{domains: array<int, string>, tones: array<int, string>, locale: string}>
     */
    private array $aiCacheMetadata = [];

    private ?DateTimeImmutable $aiLastAttemptAt = null;

    private ?DateTimeImmutable $aiLastSuccessAt = null;

    private ?string $aiLastError = null;

    /**
     * Structured log of the last AI cache operation.
     *
     * @var array{context:string|null,meta:array<string,mixed>,entries:list<array{code:string,level:string,context:array<string,mixed>}>,status:string,error:?string}|null
     */
    private ?array $aiLastLog = null;

    public function __construct(
        PDO $pdo,
        string $lexiconPath,
        TeamNameAiCacheRepository $aiCacheRepository,
        int $reservationTtlSeconds = 600,
        ?TeamNameAiClient $aiClient = null,
        bool $enableAi = true,
        ?string $defaultLocale = null,
        ?TeamNameWarmupDispatcher $teamNameWarmupDispatcher = null
    ) {
        $this->pdo = $pdo;
        $this->aiCacheRepository = $aiCacheRepository;
        $this->reservationTtlSeconds = max(60, $reservationTtlSeconds);
        $this->aiClient = $aiClient;
        $this->aiEnabled = $enableAi && $aiClient !== null;
        $locale = trim((string) ($defaultLocale ?? ''));
        $this->defaultLocale = $locale === '' ? self::DEFAULT_LOCALE : $locale;
        $this->teamNameWarmupDispatcher = $teamNameWarmupDispatcher;
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
        return $this->reserveWithBuffer(
            $eventId,
            $domains,
            $tones,
            $randomNameBuffer,
            $locale,
            self::RANDOM_NAME_STRATEGY_AI
        );
    }

    /**
     * Reserve a name for the given event with explicit buffer and strategy handling.
     *
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     * @param int $randomNameBuffer
     * @param string|null $locale
     * @param string|null $strategy
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
    public function reserveWithBuffer(
        string $eventId,
        array $domains = [],
        array $tones = [],
        int $randomNameBuffer = 0,
        ?string $locale = null,
        ?string $strategy = null
    ): array
    {
        $reservations = $this->reserveInternal(
            $eventId,
            1,
            $domains,
            $tones,
            $randomNameBuffer,
            $locale,
            $strategy
        );

        return $reservations[0];
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
        return $this->reserveBatchWithBuffer(
            $eventId,
            $count,
            $domains,
            $tones,
            $randomNameBuffer,
            $locale,
            self::RANDOM_NAME_STRATEGY_AI
        );
    }

    /**
     * Reserve multiple names for the given event with explicit buffer and strategy handling.
     *
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     * @param int $randomNameBuffer
     * @param string|null $locale
     * @param string|null $strategy
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
    public function reserveBatchWithBuffer(
        string $eventId,
        int $count,
        array $domains = [],
        array $tones = [],
        int $randomNameBuffer = 0,
        ?string $locale = null,
        ?string $strategy = null
    ): array
    {
        return $this->reserveInternal(
            $eventId,
            $count,
            $domains,
            $tones,
            $randomNameBuffer,
            $locale,
            $strategy
        );
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     * @param string|null        $locale
     * @param string|null        $strategy
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
    private function reserveInternal(
        string $eventId,
        int $count,
        array $domains,
        array $tones,
        int $randomNameBuffer,
        ?string $locale,
        ?string $strategy
    ): array
    {
        if ($eventId === '') {
            throw new InvalidArgumentException('eventId must not be empty');
        }

        $normalizedCount = max(1, min($count, 10));
        $normalizedStrategy = $this->normalizeStrategy($strategy);

        $this->releaseExpiredReservations($eventId);

        $aiCandidates = [];
        if ($this->shouldUseAi($normalizedStrategy)) {
            $aiCandidates = $this->consumeAiSuggestions(
                $eventId,
                $normalizedCount,
                $domains,
                $tones,
                $randomNameBuffer,
                $locale
            );
        }

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
        $reservations = $this->reserveCandidates(
            $eventId,
            $candidates,
            $normalizedCount,
            $totalCombinations
        );

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
                    if ($stmt instanceof PDOStatement) {
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
        $resolvedLocale = $this->resolveLocale($locale);
        $cacheKey = $this->buildAiCacheKey($eventId, $normalizedDomains, $normalizedTones, $resolvedLocale);
        $promptDomains = $this->preparePromptValues($domains);
        $promptTones = $this->preparePromptValues($tones);

        $targetSize = max(1, max($count, $buffer));
        $warmupTarget = max(self::AI_LOW_WATERMARK, $buffer);
        $scheduledWarmup = false;

        $this->loadAiCacheForEvent($eventId);

        $available = $this->aiNameCache[$cacheKey] ?? [];
        if ($available === []) {
            if ($this->teamNameWarmupDispatcher !== null) {
                if ($this->dispatchAiWarmup($eventId, $promptDomains, $promptTones, $locale, $resolvedLocale, $warmupTarget)) {
                    $scheduledWarmup = true;
                }

                return [];
            }

            $this->fillAiCache($cacheKey, $eventId, $promptDomains, $promptTones, $resolvedLocale, $targetSize);
            $available = $this->aiNameCache[$cacheKey] ?? [];
            if ($available === []) {
                return [];
            }
        } elseif ($this->teamNameWarmupDispatcher !== null) {
            if (count($available) < self::AI_LOW_WATERMARK) {
                if ($this->dispatchAiWarmup($eventId, $promptDomains, $promptTones, $locale, $resolvedLocale, $warmupTarget)) {
                    $scheduledWarmup = true;
                }
            }
        } elseif (count($available) < $targetSize) {
            $this->fillAiCache($cacheKey, $eventId, $promptDomains, $promptTones, $resolvedLocale, $targetSize);
            $available = $this->aiNameCache[$cacheKey] ?? [];
            if ($available === []) {
                return [];
            }
        }

        $selection = array_splice($this->aiNameCache[$cacheKey], 0, min($count, count($available)));

        if ($selection !== []) {
            $this->aiCacheRepository->deleteNames($eventId, $cacheKey, $selection);
        }

        if ($buffer > 0) {
            if ($this->teamNameWarmupDispatcher !== null) {
                if (!$scheduledWarmup && $this->dispatchAiWarmup($eventId, $promptDomains, $promptTones, $locale, $resolvedLocale, $warmupTarget)) {
                    $scheduledWarmup = true;
                }
            } else {
                $this->fillAiCache($cacheKey, $eventId, $promptDomains, $promptTones, $resolvedLocale, $buffer);
            }
        }

        if (
            $this->teamNameWarmupDispatcher !== null
            && !$scheduledWarmup
            && count($this->aiNameCache[$cacheKey] ?? []) < self::AI_LOW_WATERMARK
        ) {
            $this->dispatchAiWarmup($eventId, $promptDomains, $promptTones, $locale, $resolvedLocale, $warmupTarget);
        }

        return $selection;
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    private function dispatchAiWarmup(
        string $eventId,
        array $domains,
        array $tones,
        ?string $originalLocale,
        string $resolvedLocale,
        int $count
    ): bool {
        if ($this->teamNameWarmupDispatcher === null || $eventId === '' || $count <= 0) {
            return false;
        }

        $locale = $originalLocale !== null && trim($originalLocale) !== '' ? $originalLocale : $resolvedLocale;

        $this->teamNameWarmupDispatcher->dispatchWarmup($eventId, $domains, $tones, $locale, $count);

        return true;
    }

    /**
     * Request AI generated suggestions without reserving them.
     *
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return array<int, string>
     */
    public function previewAiSuggestions(
        string $eventId,
        array $domains = [],
        array $tones = [],
        ?string $locale = null,
        int $count = 5
    ): array {
        if (!$this->canUseAi() || $eventId === '') {
            return [];
        }

        $normalizedDomains = $this->normalizeFilterValues($domains);
        $normalizedTones = $this->normalizeFilterValues($tones);
        $resolvedLocale = $this->resolveLocale($locale);
        $cacheKey = $this->buildAiCacheKey($eventId, $normalizedDomains, $normalizedTones, $resolvedLocale);
        $promptDomains = $this->preparePromptValues($domains);
        $promptTones = $this->preparePromptValues($tones);

        $targetSize = max(1, min(20, $count));
        $this->loadAiCacheForEvent($eventId);
        $this->fillAiCache($cacheKey, $eventId, $promptDomains, $promptTones, $resolvedLocale, $targetSize);

        $available = $this->aiNameCache[$cacheKey] ?? [];
        if ($available === []) {
            return [];
        }

        return array_slice($available, 0, min($targetSize, count($available)));
    }

    /**
     * Warm up the AI cache for the given event and filters.
     *
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    public function warmUpAiSuggestions(
        string $eventId,
        array $domains = [],
        array $tones = [],
        ?string $locale = null,
        int $count = 5
    ): void {
        if (!$this->canUseAi() || $eventId === '') {
            return;
        }

        $normalizedDomains = $this->normalizeFilterValues($domains);
        $normalizedTones = $this->normalizeFilterValues($tones);
        $resolvedLocale = $this->resolveLocale($locale);
        $cacheKey = $this->buildAiCacheKey($eventId, $normalizedDomains, $normalizedTones, $resolvedLocale);
        $promptDomains = $this->preparePromptValues($domains);
        $promptTones = $this->preparePromptValues($tones);

        $targetSize = max(1, min(50, $count));
        $this->loadAiCacheForEvent($eventId);
        $this->fillAiCache($cacheKey, $eventId, $promptDomains, $promptTones, $resolvedLocale, $targetSize);
    }

    public function warmUpAiSuggestionsWithLog(
        string $eventId,
        array $domains = [],
        array $tones = [],
        ?string $locale = null,
        int $count = 5
    ): array {
        $this->startAiLog('warmup', [
            'event_id' => $eventId,
            'domains' => array_values($domains),
            'tones' => array_values($tones),
            'locale' => $locale,
            'count' => $count,
        ]);

        if ($eventId === '') {
            $this->finalizeAiLog('missing-event');

            return [
                'cache' => ['total' => 0, 'entries' => []],
                'log' => $this->getAiLastLog(),
            ];
        }

        if (!$this->canUseAi()) {
            $this->finalizeAiLog('disabled');

            return [
                'cache' => $this->getAiCacheState($eventId),
                'log' => $this->getAiLastLog(),
            ];
        }

        $this->warmUpAiSuggestions($eventId, $domains, $tones, $locale, $count);

        if (($this->aiLastLog['status'] ?? 'pending') === 'pending') {
            $this->finalizeAiLog('unchanged', ['count' => 0]);
        }

        return [
            'cache' => $this->getAiCacheState($eventId),
            'log' => $this->getAiLastLog(),
        ];
    }

    public function resetEventNamePreferences(string $eventId): void
    {
        if ($eventId === '') {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE team_names SET released_at = CURRENT_TIMESTAMP '
            . 'WHERE event_id = ? AND released_at IS NULL AND assigned_at IS NULL'
        );
        $stmt->execute([$eventId]);
        $stmt->closeCursor();

        $this->forgetAiCacheForEvent($eventId);
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

        $logging = $this->aiLastLog !== null;
        if ($logging && empty($this->aiLastLog['entries'])) {
            $this->appendAiLog('target', 'info', ['count' => $targetSize]);
        }

        if (!isset($this->aiNameCache[$cacheKey])) {
            $this->aiNameCache[$cacheKey] = [];
        }

        if (!isset($this->aiCacheIndex[$eventId])) {
            $this->aiCacheIndex[$eventId] = [];
        }

        if (!in_array($cacheKey, $this->aiCacheIndex[$eventId], true)) {
            $this->aiCacheIndex[$eventId][] = $cacheKey;
        }

        $this->aiCacheMetadata[$cacheKey] = [
            'domains' => array_values($domains),
            'tones' => array_values($tones),
            'locale' => $locale,
        ];

        if (count($this->aiNameCache[$cacheKey]) >= $targetSize) {
            if ($logging) {
                $this->finalizeAiLog('skipped', ['count' => count($this->aiNameCache[$cacheKey])]);
            }

            return;
        }

        $attempts = 0;
        $existingNamesForPrompt = $this->gatherExistingAiNames($cacheKey, $eventId);
        $persistedNames = [];
        $this->aiCacheLoadedEvents[$eventId] = true;

        while (count($this->aiNameCache[$cacheKey]) < $targetSize && $attempts < self::AI_MAX_ATTEMPTS) {
            $attempts++;
            $needed = $targetSize - count($this->aiNameCache[$cacheKey]);
            if ($logging) {
                $this->appendAiLog('attempt', 'info', [
                    'attempt' => $attempts,
                    'count' => $needed,
                ]);
            }

            $batch = $this->aiClient->fetchSuggestions($needed, $domains, $tones, $locale, $existingNamesForPrompt);
            $this->aiLastAttemptAt = $this->aiClient->getLastResponseAt() ?? $this->currentUtcTime();

            if ($logging) {
                $this->appendAiLog(
                    'received',
                    $batch === [] ? 'warning' : 'info',
                    [
                        'attempt' => $attempts,
                        'count' => count($batch),
                    ]
                );
            }

            if ($batch === []) {
                $this->aiLastError = $this->aiClient->getLastError() ?? 'AI service returned no suggestions.';
                if ($logging) {
                    $this->appendAiLog('error', 'error', [
                        'attempt' => $attempts,
                        'message' => $this->aiLastError,
                    ]);
                }
                break;
            }

            $added = false;
            $accepted = [];
            $skippedCache = [];
            $skippedEvent = [];
            $skippedInvalid = [];

            foreach ($batch as $candidate) {
                $normalizedName = $this->normalize($candidate);
                if ($normalizedName === '') {
                    $skippedInvalid[] = $candidate;
                    continue;
                }
                if ($this->isNameAlreadyInCache($cacheKey, $normalizedName)) {
                    $skippedCache[] = $candidate;
                    continue;
                }
                if ($this->isNameAlreadyUsed($eventId, $candidate)) {
                    $skippedEvent[] = $candidate;
                    continue;
                }

                $this->aiNameCache[$cacheKey][] = $candidate;
                $persistedNames[] = $candidate;
                $accepted[] = $candidate;

                if (!in_array($candidate, $existingNamesForPrompt, true)) {
                    $existingNamesForPrompt[] = $candidate;
                }

                $added = true;
                if (count($this->aiNameCache[$cacheKey]) >= $targetSize) {
                    break;
                }
            }

            if ($logging) {
                if ($accepted !== []) {
                    $this->appendAiLog('accepted', 'success', [
                        'attempt' => $attempts,
                        'count' => count($accepted),
                        'names' => array_map(static fn ($name): string => (string) $name, $accepted),
                    ]);
                }
                if ($skippedCache !== []) {
                    $this->appendAiLog('skipped', 'warning', [
                        'attempt' => $attempts,
                        'count' => count($skippedCache),
                        'names' => array_map(static fn ($name): string => (string) $name, $skippedCache),
                        'reason' => 'duplicate_cache',
                    ]);
                }
                if ($skippedEvent !== []) {
                    $this->appendAiLog('skipped', 'warning', [
                        'attempt' => $attempts,
                        'count' => count($skippedEvent),
                        'names' => array_map(static fn ($name): string => (string) $name, $skippedEvent),
                        'reason' => 'duplicate_event',
                    ]);
                }
                if ($skippedInvalid !== []) {
                    $this->appendAiLog('skipped', 'warning', [
                        'attempt' => $attempts,
                        'count' => count($skippedInvalid),
                        'names' => array_map(static fn ($name): string => (string) $name, $skippedInvalid),
                        'reason' => 'invalid',
                    ]);
                }
            }

            if (!$added) {
                $this->aiLastError = 'AI suggestions are already in use for this event.';
                if ($logging) {
                    $this->appendAiLog('error', 'warning', [
                        'attempt' => $attempts,
                        'message' => $this->aiLastError,
                    ]);
                }
                break;
            }

            $this->aiLastSuccessAt = $this->aiClient->getLastSuccessAt() ?? $this->aiLastAttemptAt;
            $this->aiLastError = null;
        }

        if ($persistedNames !== []) {
            $this->aiCacheRepository->persistNames(
                $eventId,
                $cacheKey,
                $persistedNames,
                $this->aiCacheMetadata[$cacheKey]
            );

            if ($logging) {
                $this->appendAiLog('persisted', 'success', [
                    'count' => count($persistedNames),
                    'names' => array_map(static fn ($name): string => (string) $name, $persistedNames),
                ]);
                $status = count($this->aiNameCache[$cacheKey]) >= $targetSize ? 'completed' : 'partial';
                $this->finalizeAiLog($status, ['count' => count($persistedNames)]);
            }

            return;
        }

        if ($logging) {
            if ($this->aiLastError !== null) {
                if (!$this->hasAiLogEntry('error')) {
                    $this->appendAiLog('error', 'error', ['message' => $this->aiLastError]);
                }
                $this->finalizeAiLog('failed', ['message' => $this->aiLastError]);
            } else {
                $this->finalizeAiLog('unchanged', ['count' => 0]);
            }
        }
    }

    private function startAiLog(string $context, array $meta = []): void
    {
        $this->aiLastLog = [
            'context' => $context,
            'meta' => $meta,
            'entries' => [],
            'status' => 'pending',
            'error' => null,
        ];
    }

    private function appendAiLog(string $code, string $level = 'info', array $context = []): void
    {
        if ($this->aiLastLog === null) {
            return;
        }

        $this->aiLastLog['entries'][] = [
            'code' => $code,
            'level' => $level,
            'context' => $context,
        ];
    }

    private function finalizeAiLog(string $status, array $context = []): void
    {
        if ($this->aiLastLog === null) {
            return;
        }

        if (($this->aiLastLog['status'] ?? 'pending') !== 'pending') {
            return;
        }

        $this->aiLastLog['status'] = $status;
        if ($status === 'failed') {
            $this->aiLastLog['error'] = $context['message'] ?? $this->aiLastError;
        } else {
            $this->aiLastLog['error'] = null;
        }

        $level = 'info';
        if ($status === 'failed') {
            $level = 'error';
        } elseif ($status === 'completed' || $status === 'partial') {
            $level = 'success';
        }

        $this->appendAiLog('status', $level, array_merge(['status' => $status], $context));
    }

    public function getAiLastLog(): array
    {
        if ($this->aiLastLog === null) {
            return [
                'context' => null,
                'meta' => [],
                'entries' => [],
                'status' => 'idle',
                'error' => null,
            ];
        }

        return $this->aiLastLog;
    }

    private function hasAiLogEntry(string $code): bool
    {
        if ($this->aiLastLog === null) {
            return false;
        }

        foreach ($this->aiLastLog['entries'] as $entry) {
            if (($entry['code'] ?? null) === $code) {
                return true;
            }
        }

        return false;
    }

    public function getAiDiagnostics(): array
    {
        $diagnostics = [
            'enabled' => $this->aiEnabled,
            'available' => false,
            'last_attempt_at' => null,
            'last_success_at' => null,
            'last_error' => null,
            'client_last_error' => null,
            'last_response_at' => null,
        ];

        if (!$this->aiEnabled || $this->aiClient === null) {
            return $diagnostics;
        }

        $lastResponse = $this->aiClient->getLastResponseAt();
        $lastSuccess = $this->aiClient->getLastSuccessAt();
        $diagnostics['last_response_at'] = $this->formatAiTimestamp($lastResponse);
        $diagnostics['last_attempt_at'] = $this->formatAiTimestamp($this->aiLastAttemptAt ?? $lastResponse);
        $diagnostics['last_success_at'] = $this->formatAiTimestamp($this->aiLastSuccessAt ?? $lastSuccess);
        $diagnostics['client_last_error'] = $this->aiClient->getLastError();
        $diagnostics['last_error'] = $this->aiLastError;

        if ($this->aiLastSuccessAt !== null && $this->aiLastAttemptAt !== null) {
            $diagnostics['available'] = $this->aiLastSuccessAt >= $this->aiLastAttemptAt;
        } elseif ($lastSuccess !== null && ($lastResponse === null || $lastSuccess >= $lastResponse)) {
            $diagnostics['available'] = true;
        }

        return $diagnostics;
    }

    public function getAiCacheState(string $eventId): array
    {
        $state = [
            'total' => 0,
            'entries' => [],
        ];

        if ($eventId === '' || !$this->canUseAi()) {
            return $state;
        }

        $this->loadAiCacheForEvent($eventId);

        if (!isset($this->aiCacheIndex[$eventId])) {
            return $state;
        }

        $entries = [];
        $total = 0;

        foreach ($this->aiCacheIndex[$eventId] as $cacheKey) {
            $names = $this->aiNameCache[$cacheKey] ?? [];
            $meta = $this->aiCacheMetadata[$cacheKey] ?? [
                'domains' => [],
                'tones' => [],
                'locale' => $this->defaultLocale,
            ];

            $count = count($names);
            $entries[] = [
                'cache_key' => $cacheKey,
                'available' => $count,
                'names' => array_values($names),
                'filters' => [
                    'domains' => $meta['domains'],
                    'tones' => $meta['tones'],
                    'locale' => $meta['locale'],
                ],
            ];

            $total += $count;
        }

        $state['entries'] = $entries;
        $state['total'] = $total;

        return $state;
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return array{total: int, reserved: int, available: int}
     */
    public function getLexiconInventory(string $eventId, array $domains = [], array $tones = []): array
    {
        if ($eventId === '') {
            return [
                'total' => 0,
                'reserved' => 0,
                'available' => 0,
            ];
        }

        $this->releaseExpiredReservations($eventId);

        $selection = $this->getNameSelection($domains, $tones);
        $total = max(0, (int) ($selection['total'] ?? 0));
        $reserved = max(0, $this->countActiveAssignments($eventId));
        $available = max(0, $total - $reserved);

        return [
            'total' => $total,
            'reserved' => $reserved,
            'available' => $available,
        ];
    }

    private function canUseAi(): bool
    {
        return $this->aiEnabled && $this->aiClient !== null;
    }

    private function shouldUseAi(string $strategy): bool
    {
        if (!$this->canUseAi()) {
            return false;
        }

        return $strategy === self::RANDOM_NAME_STRATEGY_AI;
    }

    private function formatAiTimestamp(?DateTimeImmutable $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return $timestamp->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }

    private function currentUtcTime(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function normalizeStrategy(?string $strategy): string
    {
        if ($strategy === null) {
            return self::RANDOM_NAME_STRATEGY_AI;
        }

        $candidate = strtolower(trim($strategy));
        if ($candidate === self::RANDOM_NAME_STRATEGY_AI || $candidate === self::RANDOM_NAME_STRATEGY_LEXICON) {
            return $candidate;
        }

        return self::RANDOM_NAME_STRATEGY_AI;
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    private function buildAiCacheKey(string $eventId, array $domains, array $tones, string $locale): string
    {
        $normalizedEventId = $this->normalize($eventId);
        $normalizedLocale = $this->normalize($locale);

        return sha1($normalizedEventId . '#' . implode('|', $domains) . '#' . implode('|', $tones) . '#' . $normalizedLocale);
    }

    /**
     * @return list<string>
     */
    private function gatherExistingAiNames(string $cacheKey, string $eventId): array
    {
        $cached = $this->aiNameCache[$cacheKey] ?? [];
        $known = $this->fetchKnownNames($eventId);

        $combined = array_merge($cached, $known);

        $unique = [];
        foreach ($combined as $name) {
            $candidate = trim((string) $name);
            if ($candidate === '') {
                continue;
            }
            if (!in_array($candidate, $unique, true)) {
                $unique[] = $candidate;
            }
        }

        return $unique;
    }

    /**
     * Retrieve all team names that were ever reserved for the event.
     *
     * @return list<string>
     */
    private function fetchKnownNames(string $eventId): array
    {
        if ($eventId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT name FROM team_names WHERE event_id = ?');
        $stmt->execute([$eventId]);

        $names = [];
        while (($name = $stmt->fetch(PDO::FETCH_COLUMN)) !== false) {
            $candidate = trim((string) $name);
            if ($candidate === '') {
                continue;
            }
            if (!in_array($candidate, $names, true)) {
                $names[] = $candidate;
            }
        }

        $stmt->closeCursor();

        return $names;
    }

    /**
     * @param array<int, mixed> $values Non-scalar entries are skipped.
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

    /**
     * Check if a name has already been reserved for the event regardless of its current status.
     */
    private function isNameAlreadyUsed(string $eventId, string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM team_names WHERE event_id = ? AND LOWER(name) = LOWER(?) LIMIT 1'
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

    /**
     * Retrieve the reservation history for an event.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     status: string,
     *     fallback: bool,
     *     reservation_token: string,
     *     reserved_at: ?string,
     *     assigned_at: ?string,
     *     released_at: ?string
     * }>
     */
    public function listNamesForEvent(string $eventId, ?int $limit = null): array
    {
        if ($eventId === '') {
            throw new InvalidArgumentException('eventId is required');
        }

        $limitValue = null;
        if ($limit !== null && $limit > 0) {
            $limitValue = (int) $limit;
        }

        $this->releaseExpiredReservations($eventId);

        $sql =
            'SELECT id, name, reservation_token, fallback, reserved_at, assigned_at, released_at, '
            . "CASE WHEN released_at IS NOT NULL THEN 'released' "
            . "WHEN assigned_at IS NOT NULL THEN 'assigned' ELSE 'reserved' END AS status "
            . 'FROM team_names WHERE event_id = ? ORDER BY reserved_at DESC, id DESC';

        if ($limitValue !== null) {
            $sql .= ' LIMIT ' . $limitValue;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eventId]);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'status' => (string) ($row['status'] ?? 'reserved'),
                'fallback' => ((int) ($row['fallback'] ?? 0)) === 1,
                'reservation_token' => (string) ($row['reservation_token'] ?? ''),
                'reserved_at' => $this->normalizeTimestamp($row['reserved_at'] ?? null),
                'assigned_at' => $this->normalizeTimestamp($row['assigned_at'] ?? null),
                'released_at' => $this->normalizeTimestamp($row['released_at'] ?? null),
            ];
        }

        return $history;
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
     * @param mixed $value
     */
    private function normalizeTimestamp($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($trimmed);
        } catch (Throwable $exception) {
            return $trimmed;
        }

        return $date->format(DATE_ATOM);
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
                    $names[] = $this->composeCompoundName($adj, $noun);
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

    private function composeCompoundName(string $adjective, string $noun): string
    {
        $left = $this->collapseWhitespace($adjective);
        $right = $this->collapseWhitespace($noun);

        if ($left === '') {
            return $right;
        }

        if ($right === '') {
            return $left;
        }

        return $left . $right;
    }

    private function collapseWhitespace(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', '', $trimmed);
        if ($collapsed === null) {
            return $trimmed;
        }

        return $collapsed;
    }

    /**
     * @param array<int, mixed> $filters
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
     * @param array<string, array<int, string>|mixed> $categories
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
     * @param array<string, array<int, string>|mixed> $categories
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

    private function loadAiCacheForEvent(string $eventId): void
    {
        if ($eventId === '' || isset($this->aiCacheLoadedEvents[$eventId]) || !$this->canUseAi()) {
            return;
        }

        $entries = $this->aiCacheRepository->loadForEvent($eventId);
        $this->aiCacheIndex[$eventId] = [];

        foreach ($entries as $cacheKey => $entry) {
            $names = array_values($entry['names']);
            $metadata = $entry['metadata'];

            $this->aiNameCache[$cacheKey] = $names;
            $this->aiCacheMetadata[$cacheKey] = [
                'domains' => $metadata['domains'],
                'tones' => $metadata['tones'],
                'locale' => $metadata['locale'] !== '' ? $metadata['locale'] : $this->defaultLocale,
            ];

            if (!in_array($cacheKey, $this->aiCacheIndex[$eventId], true)) {
                $this->aiCacheIndex[$eventId][] = $cacheKey;
            }
        }

        $this->aiCacheLoadedEvents[$eventId] = true;
    }

    private function forgetAiCacheForEvent(string $eventId): void
    {
        if ($eventId === '') {
            return;
        }

        $this->aiCacheRepository->deleteEvent($eventId);

        if (isset($this->aiCacheIndex[$eventId])) {
            foreach ($this->aiCacheIndex[$eventId] as $cacheKey) {
                unset($this->aiNameCache[$cacheKey]);
                unset($this->aiCacheMetadata[$cacheKey]);
            }

            unset($this->aiCacheIndex[$eventId]);
        }

        unset($this->aiCacheLoadedEvents[$eventId]);
    }
}
