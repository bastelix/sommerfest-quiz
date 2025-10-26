<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\RagChat\HttpChatResponder;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_slice;
use function count;
use function explode;
use function is_array;
use function json_decode;
use function mb_strtolower;
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
    private const MAX_FETCH_COUNT = 25;

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

        try {
            $response = $this->chatResponder->respond($messages, []);
        } catch (RuntimeException $exception) {
            return [];
        }

        $suggestions = $this->parseResponse($response, $count);

        if ($suggestions === []) {
            return [];
        }

        return array_slice($suggestions, 0, $count);
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
            return [];
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            if (array_key_exists('names', $decoded) && is_array($decoded['names'])) {
                /** @var array<int|string, mixed> $candidates */
                $candidates = $decoded['names'];
                return $this->filterCandidates($candidates, $requested);
            }

            if ($this->isSequentialArray($decoded)) {
                /** @var array<int|string, mixed> $decoded */
                return $this->filterCandidates($decoded, $requested);
            }
        }

        return $this->filterCandidates($this->parseFallbackText($response), $requested);
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
}
