<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\RagChat\HttpChatResponder;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function json_decode;
use function max;
use function mb_strtolower;
use function min;
use function preg_match;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Generates AI-backed team name suggestions.
 */
class TeamNameAiClient
{
    protected const MAX_FETCH_COUNT = 25;

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

    private ?DateTimeImmutable $lastResponseAt = null;

    private ?DateTimeImmutable $lastSuccessAt = null;

    private ?string $lastError = null;

    public function __construct(HttpChatResponder $chatResponder, ?string $model = null)
    {
        $this->chatResponder = $chatResponder;
        $this->model = $model !== null && trim($model) !== '' ? trim($model) : null;
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return list<string>
     */
    public function fetchSuggestions(int $count, array $domains, array $tones, string $locale): array
    {
        $count = max(1, min(self::MAX_FETCH_COUNT, $count));
        $locale = trim($locale) ?: 'de';
        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($locale)],
            ['role' => 'user', 'content' => $this->buildUserPrompt($count, $domains, $tones, $locale)],
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
     * @return list<array{id:string,text:string,score:float,metadata:array<string,mixed>}>|
     *     list<array<string,mixed>>
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
    private function buildUserPrompt(int $count, array $domains, array $tones, string $locale): string
    {
        $domainText = $this->formatHintList($domains);
        $toneText = $this->formatHintList($tones);

        $parts = [
            sprintf('Generate %d unique, family-friendly team names for a trivia competition.', $count),
            'Keep each suggestion short (max. three words) and avoid numbers or special characters.',
            'Only answer with valid names.',
        ];

        if ($domainText !== '') {
            $parts[] = sprintf('Prefer themes related to: %s.', $domainText);
        }

        if ($toneText !== '') {
            $parts[] = sprintf('Match the tone or mood: %s.', $toneText);
        }

        $parts[] = sprintf('Write the answer in locale "%s".', $locale);
        $parts[] = 'Respond as a JSON array of strings named "names" (example: {"names": ["Name 1", "Name 2"]}).';

        return implode(' ', $parts);
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

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            if (array_key_exists('names', $decoded) && is_array($decoded['names'])) {
                /** @var array<int|string, mixed> $candidates */
                $candidates = $decoded['names'];

                return $this->finalizeCandidates($candidates, $requested);
            }

            if ($this->isSequentialArray($decoded)) {
                /** @var array<int|string, mixed> $decoded */
                return $this->finalizeCandidates($decoded, $requested);
            }

            $this->lastError = 'JSON response missing expected "names" array.';

            return [];
        }

        $fallback = $this->parseFallbackText($response);
        if ($fallback === []) {
            $this->lastError = 'Unable to parse AI response.';

            return [];
        }

        return $this->finalizeCandidates($fallback, $requested);
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
        $lines = preg_split('/\r?\n/', $response) ?: [];
        if ($lines === []) {
            return [];
        }

        $candidates = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t\-•*\"'“”‚‘\x{00A0}");
            if ($line === '') {
                continue;
            }
            if (str_contains($line, ':')) {
                $segments = explode(':', $line, 2);
                $line = trim($segments[1]);
            }
            if ($line === '') {
                continue;
            }
            $candidates[] = $line;
        }

        return $candidates;
    }

    private function containsBlockedContent(string $lower): bool
    {
        foreach (self::BLOCKED_SUBSTRINGS as $blocked) {
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
        if ($filtered === []) {
            $this->lastError = 'AI response did not include usable suggestions.';
        } else {
            $this->lastError = null;
        }

        return $filtered;
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
