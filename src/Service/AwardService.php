<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Calculate rankings from result data and build congratulation texts.
 */
class AwardService
{
    /**
     * Compute top 3 rankings for puzzle time, catalog completion and points.
     *
     * @param list<array<string,mixed>> $results
     * @param int|null $catalogCount total number of catalogs if known
     * @return array{
     *     puzzle:list<array{team:string,place:int}>,
     *     catalog:list<array{team:string,place:int}>,
     *     points:list<array{team:string,place:int}>
     * }
     */
    public function computeRankings(array $results, ?int $catalogCount = null): array
    {
        $catalogs = [];
        $puzzleTimes = [];
        $catalogTimes = [];
        $scores = [];

        foreach ($results as $row) {
            $team = (string)($row['name'] ?? '');
            $catalog = (string)($row['catalog'] ?? '');
            $time = (int)($row['time'] ?? 0);
            $correct = (int)($row['correct'] ?? 0);
            $puzzle = isset($row['puzzleTime']) ? (int)$row['puzzleTime'] : null;

            $catalogs[$catalog] = true;

            if ($puzzle !== null) {
                if (!isset($puzzleTimes[$team]) || $puzzle < $puzzleTimes[$team]) {
                    $puzzleTimes[$team] = $puzzle;
                }
            }

            if (!isset($catalogTimes[$team][$catalog]) || $time < $catalogTimes[$team][$catalog]) {
                $catalogTimes[$team][$catalog] = $time;
            }

            if (!isset($scores[$team][$catalog]) || $correct > $scores[$team][$catalog]) {
                $scores[$team][$catalog] = $correct;
            }
        }

        $totalCatalogs = $catalogCount ?? count($catalogs);

        $puzzleList = [];
        foreach ($puzzleTimes as $team => $t) {
            $puzzleList[] = ['team' => $team, 'time' => $t];
        }
        usort($puzzleList, fn($a, $b) => $a['time'] <=> $b['time']);
        $puzzleRanks = [];
        foreach (array_slice($puzzleList, 0, 3) as $idx => $row) {
            $puzzleRanks[] = ['team' => (string)$row['team'], 'place' => $idx + 1];
        }

        $finishers = [];
        foreach ($catalogTimes as $team => $map) {
            if (count($map) === $totalCatalogs) {
                $finishers[] = ['team' => $team, 'time' => max($map)];
            }
        }
        usort($finishers, fn($a, $b) => $a['time'] <=> $b['time']);
        $catalogRanks = [];
        foreach (array_slice($finishers, 0, 3) as $idx => $row) {
            $catalogRanks[] = ['team' => (string)$row['team'], 'place' => $idx + 1];
        }

        $scoreList = [];
        foreach ($scores as $team => $map) {
            $total = array_sum($map);
            $scoreList[] = ['team' => $team, 'score' => $total];
        }
        usort($scoreList, fn($a, $b) => $b['score'] <=> $a['score']);
        $pointsRanks = [];
        foreach (array_slice($scoreList, 0, 3) as $idx => $row) {
            $pointsRanks[] = ['team' => (string)$row['team'], 'place' => $idx + 1];
        }

        return [
            'puzzle' => $puzzleRanks,
            'catalog' => $catalogRanks,
            'points' => $pointsRanks,
        ];
    }

    /**
     * Build the congratulation text for a team.
     *
     * @param string $team team name
     * @param array{
     *     puzzle:list<array{team:string,place:int}>,
     *     catalog:list<array{team:string,place:int}>,
     *     points:list<array{team:string,place:int}>
     * } $rankings
     * @param array<string,array{title:string,desc:string}>|null $info
     */
    public function buildText(string $team, array $rankings, ?array $info = null): ?string
    {
        $defaults = [
            'catalog' => [
                'title' => 'Katalogmeister',
                'desc' => 'Team, das alle Kataloge am schnellsten durchgespielt hat',
            ],
            'points' => [
                'title' => 'Highscore-Champions',
                'desc' => 'Team mit den meisten Lösungen aller Fragen',
            ],
            'puzzle' => [
                'title' => 'Rätselwort-Bestzeit',
                'desc' => 'schnellstes Lösen des Rätselworts',
            ],
        ];
        $info = $info ? $info + $defaults : $defaults;

        $lines = [];
        foreach ($rankings as $key => $list) {
            foreach ($list as $entry) {
                if ($entry['team'] === $team) {
                    $lines[] = sprintf('• %s (Platz %d): %s', $info[$key]['title'], $entry['place'], $info[$key]['desc']);
                }
            }
        }

        if ($lines === []) {
            return null;
        }

        return "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . implode("\n", $lines);
    }

    /**
     * Join a list with commas and "und" before the last item.
     *
     * @param list<string> $items
     */
    private function join(array $items): string
    {
        if (count($items) === 0) {
            return '';
        }
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);
        return implode(', ', $items) . ' und ' . $last;
    }
}
