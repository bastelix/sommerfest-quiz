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
     *     puzzle:list<string>,
     *     catalog:list<string>,
     *     points:list<string>
     * }
     */
    public function computeRankings(array $results, ?int $catalogCount = null): array
    {
        $catalogs = [];
        $puzzleTimes = [];
        $catalogTimes = [];
        $scores = [];
        $totals = [];

        foreach ($results as $row) {
            $team = (string)($row['name'] ?? '');
            $catalog = (string)($row['catalog'] ?? '');
            $time = (int)($row['time'] ?? 0);
            $correct = (int)($row['correct'] ?? 0);
            $total = (int)($row['total'] ?? 0);
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

            $ratio = $total > 0 ? $correct / $total : 0.0;
            if (!isset($scores[$team][$catalog]) || $ratio > ($totals[$team][$catalog]['ratio'] ?? -1)) {
                $scores[$team][$catalog] = $correct;
                $totals[$team][$catalog] = ['total' => $total, 'ratio' => $ratio];
            }
        }

        $totalCatalogs = $catalogCount ?? count($catalogs);

        $puzzleList = [];
        foreach ($puzzleTimes as $team => $t) {
            $puzzleList[] = ['team' => $team, 'time' => $t];
        }
        usort($puzzleList, fn($a, $b) => $a['time'] <=> $b['time']);
        $puzzleRanks = array_column(array_slice($puzzleList, 0, 3), 'team');

        $finishers = [];
        foreach ($catalogTimes as $team => $map) {
            if (count($map) === $totalCatalogs) {
                $finishers[] = ['team' => $team, 'time' => max($map)];
            }
        }
        usort($finishers, fn($a, $b) => $a['time'] <=> $b['time']);
        $catalogRanks = array_column(array_slice($finishers, 0, 3), 'team');

        $scoreList = [];
        foreach ($scores as $team => $map) {
            $correctSum = array_sum($map);
            $totalSum = array_sum(array_column($totals[$team], 'total'));
            $ratio = $totalSum > 0 ? $correctSum / $totalSum : 0.0;
            $scoreList[] = ['team' => $team, 'ratio' => $ratio];
        }
        usort($scoreList, fn($a, $b) => $b['ratio'] <=> $a['ratio']);
        $pointsRanks = array_column(array_slice($scoreList, 0, 3), 'team');

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
     *     puzzle:list<string>,
     *     catalog:list<string>,
     *     points:list<string>
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
                'desc' => 'Team mit der besten Quote richtiger Antworten',
            ],
            'puzzle' => [
                'title' => 'Rätselwort-Bestzeit',
                'desc' => 'schnellstes Lösen des Rätselworts',
            ],
        ];
        $info = $info ? $info + $defaults : $defaults;

        $placeMap = [];
        foreach ($rankings as $key => $list) {
            $pos = array_search($team, $list, true);
            if ($pos !== false) {
                $placeMap[$pos + 1][] = $key;
            }
        }

        if ($placeMap === []) {
            return null;
        }

        $sentences = [];
        if (!empty($placeMap[1])) {
            $parts = [];
            foreach ($placeMap[1] as $k) {
                $parts[] = sprintf('%s – %s', $info[$k]['title'], $info[$k]['desc']);
            }
            $sentences[] = 'Herzlichen Glückwunsch! Ihr seid ' . $this->join($parts) . '.';
        }
        if (!empty($placeMap[2])) {
            $titles = array_map(fn($k) => $info[$k]['title'], $placeMap[2]);
            $sentences[] = 'Auch in der Kategorie ' . $this->join($titles) . ' habt ihr einen tollen zweiten Platz erreicht.';
        }
        if (!empty($placeMap[3])) {
            $titles = array_map(fn($k) => $info[$k]['title'], $placeMap[3]);
            $sentences[] = 'Und in ' . $this->join($titles) . ' wart ihr unter den Top 3!';
        }

        return implode(' ', $sentences);
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
