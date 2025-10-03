<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use RuntimeException;

/**
 * Lightweight TF-IDF index that mirrors the Python implementation used for the RAG chatbot.
 */
final class SemanticIndex
{
    private const TOKEN_PATTERN = '/\\b\\w+\\b/u';

    /** @var array<int, string> */
    private array $vocabulary;

    /** @var array<int, float> */
    private array $idf;

    /** @var array<int, array{id:string,text:string,metadata:array<string,mixed>,vector:array<int,float>,norm:float}> */
    private array $chunks;

    /** @var array<string, array{index:self, mtime:int}> */
    private static array $cache = [];

    /**
     * @param array{vocabulary?:array<mixed>,idf?:array<mixed>,chunks?:array<mixed>} $payload
     */
    private function __construct(array $payload)
    {
        $this->vocabulary = array_values(array_map('strval', $payload['vocabulary'] ?? []));
        $this->idf = array_values(array_map('floatval', $payload['idf'] ?? []));
        $this->chunks = $this->buildChunks($payload['chunks'] ?? []);
    }

    public static function load(string $path): self
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            throw new RuntimeException(sprintf('Semantic index not found: %s', $path));
        }

        $mtime = (int) filemtime($realPath);
        $cached = self::$cache[$realPath] ?? null;
        if ($cached !== null && $cached['mtime'] === $mtime) {
            return $cached['index'];
        }

        $json = file_get_contents($realPath);
        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to read semantic index: %s', $realPath));
        }

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid semantic index payload.');
        }

        $instance = new self($payload);
        self::$cache[$realPath] = ['index' => $instance, 'mtime' => $mtime];

        return $instance;
    }

    /**
     * Perform a cosine similarity search on the stored chunks.
     *
     * @return list<SearchResult>
     */
    public function search(string $query, int $topK = 5, float $minScore = 0.0): array
    {
        $vector = $this->vectorise($query);
        if ($vector === []) {
            return [];
        }

        $queryNorm = 0.0;
        foreach ($vector as $weight) {
            $queryNorm += $weight * $weight;
        }
        $queryNorm = $queryNorm > 0.0 ? sqrt($queryNorm) : 0.0;
        if ($queryNorm === 0.0) {
            return [];
        }

        $results = [];
        foreach ($this->chunks as $chunk) {
            $norm = $chunk['norm'];
            if ($norm <= 0.0) {
                continue;
            }

            $score = $this->dot($chunk['vector'], $vector);
            if ($score <= 0.0) {
                continue;
            }

            $similarity = $score / ($norm * $queryNorm);
            if ($similarity < $minScore) {
                continue;
            }

            $results[] = new SearchResult(
                $chunk['id'],
                round($similarity, 6),
                $chunk['text'],
                $chunk['metadata']
            );
        }

        usort($results, static fn (SearchResult $a, SearchResult $b): int => $b->getScore() <=> $a->getScore());

        return array_slice($results, 0, max(0, $topK));
    }

    /**
     * @param array<int, mixed> $items
     *
     * @return array<int, array{id:string,text:string,metadata:array<string,mixed>,vector:array<int,float>,norm:float}>
     */
    private function buildChunks(array $items): array
    {
        $chunks = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $vector = [];
            if (isset($item['vector']) && is_array($item['vector'])) {
                foreach ($item['vector'] as $pair) {
                    if (!is_array($pair) || count($pair) !== 2) {
                        continue;
                    }
                    $index = (int) $pair[0];
                    $weight = (float) $pair[1];
                    $vector[$index] = $weight;
                }
            }

            ksort($vector, SORT_NUMERIC);

            $metadata = [];
            if (isset($item['metadata']) && is_array($item['metadata'])) {
                /** @var array<string, mixed> $meta */
                $meta = $item['metadata'];
                $metadata = $meta;
            }

            $chunks[] = [
                'id' => (string) ($item['id'] ?? ''),
                'text' => (string) ($item['text'] ?? ''),
                'metadata' => $metadata,
                'vector' => $vector,
                'norm' => isset($item['norm']) ? (float) $item['norm'] : 0.0,
            ];
        }

        return $chunks;
    }

    /**
     * @return array<int, float>
     */
    private function vectorise(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $lowercase = mb_strtolower($text, 'UTF-8');

        $matches = [];
        $matchCount = preg_match_all(self::TOKEN_PATTERN, $lowercase, $matches);
        if ($matchCount === false || $matchCount === 0) {
            return [];
        }

        /** @var list<string> $tokens */
        $tokens = $matches[0];
        if ($tokens === []) {
            return [];
        }

        $counts = [];
        foreach ($tokens as $token) {
            $index = array_search($token, $this->vocabulary, true);
            if ($index === false) {
                continue;
            }
            $counts[$index] = ($counts[$index] ?? 0) + 1;
        }

        if ($counts === []) {
            return [];
        }

        $total = array_sum($counts);

        $vector = [];
        foreach ($counts as $index => $count) {
            $tf = $count / $total;
            $idf = $this->idf[$index] ?? 0.0;
            $vector[$index] = $tf * $idf;
        }

        return $vector;
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    private function dot(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($b as $index => $weight) {
            if (isset($a[$index])) {
                $sum += $a[$index] * $weight;
            }
        }

        return $sum;
    }
}
