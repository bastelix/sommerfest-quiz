<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\RagChat\HttpChatResponder;
use App\Support\UsernameGuard;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

use function array_filter;
use function array_fill;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_pop;
use function array_shift;
use function array_slice;
use function array_unique;
use function array_values;
use function array_unshift;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function json_decode;
use function max;
use function mb_substr;
use function mb_strtolower;
use function min;
use function preg_match;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_contains;
use function strlen;
use function substr;
use function trim;
use function usort;

/**
 * Generates AI-backed team name suggestions.
 */
class TeamNameAiClient
{
    protected const MAX_FETCH_COUNT = 25;

    public const EXISTING_NAMES_LIMIT = 100;

    private const BLOCKED_SUBSTRINGS = [
        'fuck',
        'shit',
        'arsch',
        'bitch',
        'cunt',
        'penis',
        'vagina',
        'dick',
        'pimmel',
        'hitler',
        'nazi',
        'sex',
        'porn',
        'xxx',
        'fick',
    ];

    private HttpChatResponder $chatResponder;

    private ?string $model;

    private ?PDO $pdo;

    /**
     * @var list<string>
     */
    private array $databaseBlockedTerms = [];

    private bool $databaseLoaded = false;

    private ?DateTimeImmutable $lastResponseAt = null;

    private ?DateTimeImmutable $lastSuccessAt = null;

    private ?string $lastError = null;

    public function __construct(HttpChatResponder $chatResponder, ?string $model = null, ?PDO $pdo = null)
    {
        $this->chatResponder = $chatResponder;
        $this->model = $model !== null && trim($model) !== '' ? trim($model) : null;
        $this->pdo = $pdo;
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
        $locale = trim($locale) ?: 'de';
        $existingNames = $this->prepareExistingNames($existingNames);
        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($locale)],
            ['role' => 'user', 'content' => $this->buildUserPrompt($count, $domains, $tones, $locale, $existingNames)],
        ];

        $context = $this->buildContextPayload($count, $domains, $tones, $locale);

        try {
            $response = $this->chatResponder->respond($messages, $context);
        } catch (RuntimeException $exception) {
            $this->recordFailure($exception->getMessage());

            return [];
        }

        $suggestions = $this->parseResponse($response, $count);
        if ($suggestions === []) {
            $error = $this->lastError ?? 'AI responder returned no usable suggestions.';
            $this->recordFailure($error);

            return [];
        }

        $this->recordSuccess();

        return array_slice($suggestions, 0, $count);
    }

    public function getLastResponseAt(): ?DateTimeImmutable
    {
        return $this->lastResponseAt;
    }

    public function getLastSuccessAt(): ?DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return list<array{id:string,text:string,score:float,metadata:array<string,mixed>}>|list<array<string,mixed>>
     */
    private function buildContextPayload(int $count, array $domains, array $tones, string $locale): array
    {
        $locale = trim($locale) ?: 'de';
        $normalizedDomains = $this->normaliseContextValues($domains);
        $normalizedTones = $this->normaliseContextValues($tones);

        $summaryParts = [
            sprintf('Team name request: %d suggestions for locale "%s".', $count, $locale),
        ];

        if ($normalizedDomains === []) {
            $summaryParts[] = 'Domains: (unspecified).';
        } else {
            $summaryParts[] = 'Domains: ' . implode(', ', $normalizedDomains) . '.';
        }

        if ($normalizedTones === []) {
            $summaryParts[] = 'Tones: (unspecified).';
        } else {
            $summaryParts[] = 'Tones: ' . implode(', ', $normalizedTones) . '.';
        }

        return [[
            'id' => 'team-name-request',
            'text' => trim(implode(' ', $summaryParts)),
            'score' => 1.0,
            'metadata' => [
                'count' => $count,
                'locale' => $locale,
                'domains' => $normalizedDomains,
                'tones' => $normalizedTones,
            ],
        ]];
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    private const PROMPT_NAME_MAX_LENGTH = 30;

    private function buildUserPrompt(int $count, array $domains, array $tones, string $locale, array $existingNames): string
    {
        $domainText = $this->formatHintList($domains);
        $toneText = $this->formatHintList($tones);
        $theme = $this->buildThemeText($domainText, $toneText);
        $blacklist = $this->formatBlacklistTerms();

        $lines = [
            sprintf('Erfinde %d einzigartige, familienfreundliche Spielernamen zum Thema %s', $count, $theme),
            'Stil: humorvoll, cleveres Wortspiel, kurze Alliteration ok.',
            'Sprache: Deutsch.',
            sprintf('Vermeide reale Personen/Marken, politische/sexuelle Inhalte, Gewalt, Beleidigungen und alles aus dieser Blacklist: %s.', $blacklist),
            'Formate: nur JSON-Array aus Strings, keine Erklärungen.',
            sprintf('Länge pro Name: max. %d Zeichen.', self::PROMPT_NAME_MAX_LENGTH),
        ];

        if ($domainText !== '') {
            $lines[] = sprintf('Optional: Beziehe folgende Sportarten/Begriffe ein: %s.', $domainText);
        }

        if ($locale !== '') {
            $lines[] = sprintf('Nutze ausschließlich die Sprache "%s".', $locale);
        }

        if ($existingNames !== []) {
            $lines[] = 'Bereits vorhandene oder verwendete Namen (nicht wiederverwenden):';
            $lines[] = $this->formatExistingNames($existingNames);
            $lines[] = sprintf('Liefere genau %d komplett neue Namen, die keinen der oben genannten Namen wiederholen.', $count);
        } else {
            $lines[] = sprintf('Liefere genau %d komplett neue Namen, alle voneinander verschieden.', $count);
        }

        $lines[] = 'Keine Duplikate, keine Zahlenkolonnen.';
        $lines[] = 'Beispiele für den gewünschten Ton (nicht wiederverwenden):';
        $lines[] = '["Dribbel-Dachs","Volley-Viech","Sprint-Sultan","Tor-Tornado","Kreidekreisläufer"]';

        return implode("\n", $lines);
    }

    /**
     * @param array<int, string> $existingNames
     *
     * @return list<string>
     */
    private function prepareExistingNames(array $existingNames): array
    {
        $prepared = [];
        foreach ($existingNames as $name) {
            $candidate = trim((string) $name);
            if ($candidate === '') {
                continue;
            }
            if (!in_array($candidate, $prepared, true)) {
                $prepared[] = $candidate;
            }
        }

        if (count($prepared) > self::EXISTING_NAMES_LIMIT) {
            $prepared = array_slice($prepared, 0, self::EXISTING_NAMES_LIMIT);
        }

        return $prepared;
    }

    /**
     * @param list<string> $existingNames
     */
    private function formatExistingNames(array $existingNames): string
    {
        $lines = [];
        foreach ($existingNames as $index => $name) {
            $lines[] = sprintf('%d. %s', $index + 1, $name);
        }

        return implode("\n", $lines);
    }

    private function buildSystemPrompt(string $locale): string
    {
        $prompt = 'You are an assistant that creates inclusive, respectful and positive team names.'
            . ' Do not include offensive or political language. Only output the requested JSON structure.';

        if ($this->model !== null) {
            $prompt .= ' Optimise the suggestions for the model "' . $this->model . '".';
        }

        if ($locale !== '') {
            $prompt .= ' Prefer natural language appropriate for locale "' . $locale . '".';
        }

        return $prompt;
    }

    /**
     * @return list<string>
     */
    private function parseResponse(string $response, int $requested): array
    {
        $response = trim($response);
        if ($response === '') {
            $this->lastError = 'AI response was empty.';

            return [];
        }

        $jsonPayload = $this->prepareJsonPayload($response);
        $decoded = json_decode($jsonPayload, true);
        if (is_array($decoded)) {
            $primaryCandidates = null;

            if (array_key_exists('names', $decoded) && is_array($decoded['names'])) {
                /** @var array<int|string, mixed> $primaryCandidates */
                $primaryCandidates = $decoded['names'];
            } elseif ($this->isSequentialArray($decoded)) {
                /** @var array<int|string, mixed> $decoded */
                $primaryCandidates = $decoded;
            }

            if ($primaryCandidates === null) {
                $this->lastError = 'JSON response missing expected "names" array.';

                return [];
            }

            $result = $this->finalizeCandidates($primaryCandidates, $requested);
            if (count($result) >= $requested) {
                return array_slice($result, 0, $requested);
            }

            $fallback = $this->parseFallbackText($response);
            if ($fallback === [] && $jsonPayload !== '') {
                $fallback = $this->parseFallbackText($jsonPayload);
            }

            if ($fallback !== []) {
                /** @var array<int|string, mixed> $primaryCandidates */
                $combined = array_merge($primaryCandidates, $fallback);

                return $this->finalizeCandidates($combined, $requested);
            }

            return $result;
        }

        $fallback = $this->parseFallbackText($response);
        if ($fallback === [] && $jsonPayload !== '') {
            $fallback = $this->parseFallbackText($jsonPayload);
        }
        if ($fallback === []) {
            $this->lastError = 'Unable to parse AI response.';

            return [];
        }

        return $this->finalizeCandidates($fallback, $requested);
    }

    private function prepareJsonPayload(string $response): string
    {
        $payload = trim($response);
        if ($payload === '') {
            return '';
        }

        $payload = $this->stripCodeFence($payload);

        $extracted = $this->extractFirstJsonSegment($payload);
        if ($extracted !== null) {
            $payload = $extracted;
        }

        $payload = trim($payload);
        $payload = preg_replace('/^json\s*[:=]\s*/i', '', $payload, 1) ?? $payload;

        return trim($payload);
    }

    private function extractFirstJsonSegment(string $text): ?string
    {
        $length = strlen($text);
        $start = null;
        $stack = [];
        $inString = false;
        $escape = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $text[$index];

            if ($start === null) {
                if ($char === '{' || $char === '[') {
                    $start = $index;
                    $stack[] = $char;
                }

                continue;
            }

            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }

                if ($char === '\\') {
                    $escape = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{' || $char === '[') {
                $stack[] = $char;

                continue;
            }

            if ($char !== '}' && $char !== ']') {
                continue;
            }

            if ($stack === []) {
                return null;
            }

            $opening = array_pop($stack);
            if (($opening === '{' && $char !== '}') || ($opening === '[' && $char !== ']')) {
                return null;
            }

            if ($stack !== []) {
                continue;
            }

            $segment = substr($text, $start, $index - $start + 1);

            return $segment;
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $candidates
     * @return list<string>
     */
    private function filterCandidates(array $candidates, int $limit): array
    {
        $accepted = [];
        $normalized = [];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $name = $this->normaliseName($candidate);
            if ($name === '') {
                continue;
            }
            $lower = mb_strtolower($name);
            if (isset($normalized[$lower])) {
                continue;
            }
            if ($this->containsBlockedContent($lower)) {
                continue;
            }
            $accepted[] = $name;
            $normalized[$lower] = true;
            if (count($accepted) >= $limit) {
                break;
            }
        }

        return $accepted;
    }

    /**
     * @return list<string>
     */
    private function parseFallbackText(string $response): array
    {
        $response = trim($response);
        if ($response === '') {
            return [];
        }

        $response = $this->stripCodeFence($response);

        $lines = preg_split('/\r?\n/', $response) ?: [];
        if ($lines === []) {
            return [];
        }

        $candidates = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t\-•*`\"'“”‚‘\x{00A0}");
            if ($line === '') {
                continue;
            }
            if (str_contains($line, ':')) {
                $segments = explode(':', $line, 2);
                $line = trim($segments[1]);
            }
            $line = preg_replace('/^\(?\d+[\s\.\)\]\-]*\s*/u', '', $line) ?? $line;
            $line = preg_replace('/^\d+\s+/u', '', $line) ?? $line;
            $line = str_replace('`', '', $line);
            $line = trim($line, " \t\-•*\"'“”‚‘\x{00A0}");
            if ($line === '') {
                continue;
            }
            $jsonSegment = $this->extractFirstJsonSegment($line);
            if ($jsonSegment !== null) {
                $decoded = json_decode($jsonSegment, true);
                if (is_array($decoded) && $this->appendFallbackDecoded($decoded, $candidates)) {
                    continue;
                }
            }
            $candidates[] = $line;
        }

        return $candidates;
    }

    /**
     * @param array<int|string, mixed> $decoded
     * @param array<int, string>        $candidates
     */
    private function appendFallbackDecoded(array $decoded, array &$candidates): bool
    {
        if (array_key_exists('names', $decoded) && is_array($decoded['names'])) {
            $appended = false;
            foreach ($decoded['names'] as $value) {
                if (!is_string($value)) {
                    continue;
                }
                $candidates[] = $value;
                $appended = true;
            }

            return $appended;
        }

        if ($this->isSequentialArray($decoded)) {
            $appended = false;
            foreach ($decoded as $value) {
                if (!is_string($value)) {
                    continue;
                }
                $candidates[] = $value;
                $appended = true;
            }

            return $appended;
        }

        return false;
    }

    private function stripCodeFence(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/```[^\n]*\n?([\s\S]*?)```/u', $text, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        if (preg_match('/^```/u', $text) === 1) {
            $lines = preg_split('/\R/', $text) ?: [];
            if ($lines === []) {
                return '';
            }

            $rawFirstLine = (string) array_shift($lines);
            $firstLine = trim($rawFirstLine);
            if (preg_match('/^```(?:\s*[a-z0-9_-]+)?$/i', $firstLine) !== 1) {
                array_unshift($lines, $rawFirstLine);

                return trim(implode("\n", $lines));
            }

            while ($lines !== [] && trim((string) end($lines)) === '') {
                array_pop($lines);
            }

            if ($lines !== [] && trim((string) end($lines)) === '```') {
                array_pop($lines);
            }

            return trim(implode("\n", $lines));
        }

        return $text;
    }

    private function containsBlockedContent(string $lower): bool
    {
        foreach ($this->getBlacklistTerms() as $blocked) {
            if (str_contains($lower, $blocked)) {
                return true;
            }
        }

        if (str_contains($lower, 'http://') || str_contains($lower, 'https://')) {
            return true;
        }

        if (preg_match('/[0-9<>\\[\\]{}]/u', $lower) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param array<int, string> $values
     */
    private function formatHintList(array $values): string
    {
        $values = array_map(static fn ($value): string => trim((string) $value), $values);
        $values = array_filter($values, static fn (string $value): bool => $value !== '');
        if ($values === []) {
            return '';
        }

        if (count($values) === 1) {
            return $values[0];
        }

        $last = array_pop($values);
        return implode(', ', $values) . ' und ' . $last;
    }

    /**
     * @param array<int, string> $values
     *
     * @return list<string>
     */
    private function normaliseContextValues(array $values): array
    {
        $values = array_map(static fn ($value): string => trim((string) $value), $values);
        $values = array_filter($values, static fn (string $value): bool => $value !== '');

        /** @var list<string> $values */
        $values = array_values(array_unique($values));

        return $values;
    }

    private function normaliseName(string $candidate): string
    {
        $candidate = trim(preg_replace('/\s+/u', ' ', $candidate) ?? '');
        if ($candidate === '') {
            return '';
        }

        if (preg_match("/^[\p{L}\s\-'.]{2,48}$/u", $candidate) !== 1) {
            return '';
        }

        return $candidate;
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private function isSequentialArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * @param array<int|string, mixed> $candidates
     * @return list<string>
     */
    private function finalizeCandidates(array $candidates, int $requested): array
    {
        $filtered = $this->filterCandidates($candidates, $requested);
        if ($filtered !== []) {
            $filtered = $this->mixCandidates($filtered);
        }
        if ($filtered === []) {
            $this->lastError = 'AI response did not include usable suggestions.';
        } else {
            $this->lastError = null;
        }

        return $filtered;
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function mixCandidates(array $names): array
    {
        if (count($names) <= 2) {
            return $names;
        }

        $buckets = [];
        foreach ($names as $name) {
            $token = $this->extractPrimaryToken($name);
            $buckets[$token][] = $name;
        }

        $result = [];
        $lastToken = null;
        $total = count($names);

        while (count($result) < $total && $buckets !== []) {
            $token = $this->selectNextToken($buckets, $lastToken);
            if ($token === null) {
                break;
            }

            $result[] = array_shift($buckets[$token]);
            if ($buckets[$token] === []) {
                unset($buckets[$token]);
            }

            $lastToken = $token;
        }

        if (count($result) < $total) {
            foreach ($names as $name) {
                if (!in_array($name, $result, true)) {
                    $result[] = $name;
                }
            }
        }

        return $result;
    }

    private function buildThemeText(string $domainText, string $toneText): string
    {
        if ($domainText !== '' && $toneText !== '') {
            return $domainText . ' (Stimmung: ' . $toneText . ')';
        }

        if ($domainText !== '') {
            return $domainText;
        }

        if ($toneText !== '') {
            return 'Stimmung ' . $toneText;
        }

        return 'Sommerfest-Quiz';
    }

    private function formatBlacklistTerms(): string
    {
        return implode(', ', $this->getBlacklistTerms());
    }

    /**
     * @return list<string>
     */
    private function getBlacklistTerms(): array
    {
        $this->loadDatabaseBlockedTerms();

        return array_values(array_unique(array_merge(self::BLOCKED_SUBSTRINGS, $this->databaseBlockedTerms)));
    }

    private function loadDatabaseBlockedTerms(): void
    {
        if ($this->databaseLoaded || $this->pdo === null) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count(UsernameGuard::DATABASE_CATEGORIES), '?'));
        $sql = sprintf('SELECT term FROM username_blocklist WHERE category IN (%s)', $placeholders);

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            $this->databaseLoaded = true;
            return;
        }

        try {
            $stmt->execute(UsernameGuard::DATABASE_CATEGORIES);
        } catch (PDOException) {
            $this->databaseLoaded = true;
            return;
        }

        $terms = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $term = mb_strtolower(trim((string) ($row['term'] ?? '')));
            if ($term === '') {
                continue;
            }
            $terms[] = $term;
        }

        if ($terms !== []) {
            $this->databaseBlockedTerms = array_values(array_unique($terms));
        }

        $this->databaseLoaded = true;
    }

    private const STOP_WORDS = [
        'der',
        'die',
        'das',
        'den',
        'dem',
        'des',
        'ein',
        'eine',
        'einer',
        'einem',
        'einen',
        'eines',
        'the',
        'team',
    ];

    private function extractPrimaryToken(string $name): string
    {
        $normalized = mb_strtolower($name);
        $normalized = preg_replace('/[^\p{L}\s\-]/u', '', $normalized) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return '#';
        }

        $parts = preg_split('/[\s\-]+/u', $normalized) ?: [];
        $fallback = null;

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (in_array($part, self::STOP_WORDS, true)) {
                if ($fallback === null) {
                    $fallback = $part;
                }
                continue;
            }

            return mb_substr($part, 0, 3);
        }

        if ($fallback !== null) {
            return mb_substr($fallback, 0, 3);
        }

        return '#';
    }

    /**
     * @param array<string, list<string>> $buckets
     */
    private function selectNextToken(array $buckets, ?string $excludeToken): ?string
    {
        $candidates = [];
        foreach ($buckets as $token => $names) {
            $candidates[] = ['token' => $token, 'count' => count($names)];
        }

        usort($candidates, static function (array $left, array $right): int {
            if ($left['count'] === $right['count']) {
                return $left['token'] <=> $right['token'];
            }

            return $right['count'] <=> $left['count'];
        });

        foreach ($candidates as $candidate) {
            if ($excludeToken === null || $candidate['token'] !== $excludeToken) {
                return $candidate['token'];
            }
        }

        return $candidates[0]['token'] ?? null;
    }

    protected function recordSuccess(?DateTimeImmutable $timestamp = null): void
    {
        $time = $timestamp ?? $this->currentTime();
        $this->lastResponseAt = $time;
        $this->lastSuccessAt = $time;
        $this->lastError = null;
    }

    protected function recordFailure(string $message, ?DateTimeImmutable $timestamp = null): void
    {
        $time = $timestamp ?? $this->currentTime();
        $this->lastResponseAt = $time;
        $this->lastError = trim($message) === '' ? 'AI responder returned an unknown error.' : $message;
    }

    protected function currentTime(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
