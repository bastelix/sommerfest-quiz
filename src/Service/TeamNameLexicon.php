<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

use function array_keys;
use function array_merge;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_object;
use function preg_replace;
use function sha1;
use function trim;

/**
 * Manages the team name lexicon: loading word lists, filtering by category,
 * and generating compound name combinations.
 */
class TeamNameLexicon
{
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

    /**
     * Cache of computed name combinations per filter set.
     *
     * @var array<string, array{adjectives: array<int, string>, nouns: array<int, string>, names: array<int, string>, total: int}>
     */
    private array $nameCache = [];

    public function __construct(string $lexiconPath)
    {
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
     * Return inventory statistics for the lexicon.
     *
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return array{adjectives: int, nouns: int, total: int, version: int}
     */
    public function getInventory(array $domains = [], array $tones = []): array
    {
        $selection = $this->getNameSelection($domains, $tones);

        return [
            'adjectives' => count($selection['adjectives']),
            'nouns' => count($selection['nouns']),
            'total' => $selection['total'],
            'version' => $this->lexiconVersion,
        ];
    }

    /**
     * Get the name selection (adjectives, nouns, combinations) for given filters.
     *
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     *
     * @return array{adjectives: array<int, string>, nouns: array<int, string>, names: array<int, string>, total: int}
     */
    public function getNameSelection(array $domains, array $tones): array
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

    public function composeCompoundName(string $adjective, string $noun): string
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

    /**
     * @param array<int, mixed> $filters
     *
     * @return array<int, string>
     */
    public function normalizeFilterValues(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $filter) {
            if (is_array($filter) || is_object($filter)) {
                continue;
            }
            $value = $this->normalizeWord((string) $filter);
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
     * @param array<mixed> $domains
     * @param array<mixed> $tones
     */
    public function buildCacheKey(array $domains, array $tones): string
    {
        return sha1(implode('|', $domains) . '#' . implode('|', $tones));
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
            $categoryKey = $this->normalizeWord($key);
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

    private function normalizeWord(string $value): string
    {
        return mb_strtolower(trim($value));
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
}
