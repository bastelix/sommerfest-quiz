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
     * @param list<array<string,mixed>> $questionResults
     * @return array{
     *     puzzle:list<array{team:string,place:int}>,
     *     catalog:list<array{team:string,place:int}>,
     *     points:list<array{team:string,place:int}>,
     *     accuracy:list<array{team:string,place:int}>
     * }
     */
    public function computeRankings(array $results, ?int $catalogCount = null, array $questionResults = []): array {
        $catalogs = [];
        $puzzleTimes = [];
        $catalogDurations = [];
        $catalogFinishTimes = [];
        $scores = [];
        $attemptMetrics = [];

        foreach ($questionResults as $row) {
            $team = (string)($row['name'] ?? '');
            $catalog = (string)($row['catalog'] ?? '');
            if ($team === '' || $catalog === '') {
                continue;
            }
            $attempt = (int)($row['attempt'] ?? 1);
            $key = $team . '|' . $catalog . '|' . $attempt;
            $finalPointsRaw = $row['final_points'] ?? $row['finalPoints'] ?? $row['points'] ?? 0;
            $finalPoints = (int) $finalPointsRaw;
            $efficiencyRaw = $row['efficiency'] ?? null;
            $efficiency = $efficiencyRaw !== null ? (float) $efficiencyRaw : ((int)($row['correct'] ?? 0) === 1 ? 1.0 : 0.0);
            if (!isset($attemptMetrics[$key])) {
                $attemptMetrics[$key] = [
                    'points' => 0,
                    'efficiencySum' => 0.0,
                    'questionCount' => 0,
                ];
            }
            $attemptMetrics[$key]['points'] += max(0, $finalPoints);
            $attemptMetrics[$key]['efficiencySum'] += max(0.0, $efficiency);
            $attemptMetrics[$key]['questionCount']++;
        }

        foreach ($results as $row) {
            $team = (string)($row['name'] ?? '');
            $catalog = (string)($row['catalog'] ?? '');
            if ($team === '' || $catalog === '') {
                continue;
            }
            $time = (int)($row['time'] ?? 0);
            $correct = (int)($row['correct'] ?? 0);
            $points = isset($row['points']) ? (int)$row['points'] : $correct;
            $puzzle = isset($row['puzzleTime']) ? (int)$row['puzzleTime'] : null;
            $attempt = (int)($row['attempt'] ?? 1);
            $key = $team . '|' . $catalog . '|' . $attempt;
            $durationRaw = $row['duration_sec'] ?? $row['durationSec'] ?? null;
            $duration = null;
            if ($durationRaw !== null && $durationRaw !== '') {
                if (is_numeric($durationRaw)) {
                    $duration = (int) round((float) $durationRaw);
                    if ($duration < 0) {
                        $duration = 0;
                    }
                }
            }
            $summary = $attemptMetrics[$key] ?? null;
            if ($summary !== null) {
                $finalPoints = (int) $summary['points'];
                $effSum = (float) $summary['efficiencySum'];
                $questionCount = max(0, (int) $summary['questionCount']);
            } else {
                $finalPoints = $points;
                $questionCount = (int)($row['total'] ?? 0);
                if ($questionCount < 0) {
                    $questionCount = 0;
                }
                if ($questionCount === 0) {
                    $avgFallback = 0.0;
                    $effSum = 0.0;
                } else {
                    $avgFallback = $correct / $questionCount;
                    $effSum = $avgFallback * $questionCount;
                }
            }
            $average = $questionCount === 0 ? 0.0 : $effSum / $questionCount;

            $catalogs[$catalog] = true;

            if ($puzzle !== null) {
                if (!isset($puzzleTimes[$team]) || $puzzle < $puzzleTimes[$team]) {
                    $puzzleTimes[$team] = $puzzle;
                }
            }

            if ($duration !== null) {
                if (!isset($catalogDurations[$team][$catalog]) || $duration < $catalogDurations[$team][$catalog]) {
                    $catalogDurations[$team][$catalog] = $duration;
                }
            }

            if (!isset($catalogFinishTimes[$team][$catalog]) || $time < $catalogFinishTimes[$team][$catalog]) {
                $catalogFinishTimes[$team][$catalog] = $time;
            }

            $existing = $scores[$team][$catalog] ?? null;
            if (
                $existing === null
                || $finalPoints > $existing['points']
                || ($finalPoints === $existing['points'] && $average > $existing['average'])
            ) {
                $scores[$team][$catalog] = [
                    'points' => $finalPoints,
                    'average' => $average,
                    'efficiencySum' => $effSum,
                    'questionCount' => $questionCount,
                ];
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
        $allTeams = array_unique(array_merge(array_keys($catalogDurations), array_keys($catalogFinishTimes)));
        foreach ($allTeams as $teamName) {
            $durationMap = $catalogDurations[$teamName] ?? [];
            $finishMap = $catalogFinishTimes[$teamName] ?? [];
            if ($totalCatalogs === 0) {
                continue;
            }

            $hasDuration = count($durationMap) === $totalCatalogs;
            $totalDuration = 0;
            if ($hasDuration) {
                foreach ($durationMap as $value) {
                    if ($value === null) {
                        $hasDuration = false;
                        break;
                    }
                    $totalDuration += (int) $value;
                }
            }

            if ($hasDuration) {
                $latestFinish = null;
                foreach ($finishMap as $finish) {
                    if ($latestFinish === null || $finish > $latestFinish) {
                        $latestFinish = $finish;
                    }
                }
                $finishers[] = ['team' => $teamName, 'duration' => $totalDuration, 'finished' => $latestFinish];
                continue;
            }

            if (count($finishMap) === $totalCatalogs) {
                $latest = null;
                foreach ($finishMap as $finish) {
                    if ($latest === null || $finish > $latest) {
                        $latest = $finish;
                    }
                }
                if ($latest !== null) {
                    $finishers[] = ['team' => $teamName, 'duration' => null, 'finished' => $latest];
                }
            }
        }

        usort(
            $finishers,
            static function (array $a, array $b): int {
                $aHasDuration = $a['duration'] !== null;
                $bHasDuration = $b['duration'] !== null;
                if ($aHasDuration && $bHasDuration) {
                    $cmp = $a['duration'] <=> $b['duration'];
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    $aFinish = $a['finished'] ?? PHP_INT_MAX;
                    $bFinish = $b['finished'] ?? PHP_INT_MAX;
                    return $aFinish <=> $bFinish;
                }

                if ($aHasDuration) {
                    return -1;
                }

                if ($bHasDuration) {
                    return 1;
                }

                $aFinish = $a['finished'] ?? PHP_INT_MAX;
                $bFinish = $b['finished'] ?? PHP_INT_MAX;
                return $aFinish <=> $bFinish;
            }
        );

        $catalogRanks = [];
        foreach (array_slice($finishers, 0, 3) as $idx => $row) {
            $catalogRanks[] = ['team' => (string) $row['team'], 'place' => $idx + 1];
        }

        $scoreList = [];
        $accuracyCandidates = [];
        foreach ($scores as $team => $map) {
            $total = 0;
            $effSumTotal = 0.0;
            $questionCountTotal = 0;
            foreach ($map as $entry) {
                $total += (int) $entry['points'];
                $effSumTotal += (float) $entry['efficiencySum'];
                $questionCountTotal += (int) $entry['questionCount'];
            }
            $avgEfficiency = $questionCountTotal === 0 ? 0.0 : $effSumTotal / $questionCountTotal;
            $scoreList[] = ['team' => $team, 'score' => $total, 'avgEfficiency' => $avgEfficiency];
            if ($questionCountTotal > 0) {
                $accuracyCandidates[] = [
                    'team' => $team,
                    'avgEfficiency' => $avgEfficiency,
                    'questionCount' => $questionCountTotal,
                    'score' => $total,
                ];
            }
        }
        usort(
            $scoreList,
            static function (array $a, array $b): int {
                $cmp = $b['score'] <=> $a['score'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                return $b['avgEfficiency'] <=> $a['avgEfficiency'];
            }
        );
        $pointsRanks = [];
        foreach (array_slice($scoreList, 0, 3) as $idx => $row) {
            $pointsRanks[] = ['team' => (string)$row['team'], 'place' => $idx + 1];
        }

        usort(
            $accuracyCandidates,
            static function (array $a, array $b): int {
                $cmp = $b['avgEfficiency'] <=> $a['avgEfficiency'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                $cmp = $b['questionCount'] <=> $a['questionCount'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                $cmp = $b['score'] <=> $a['score'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                return $a['team'] <=> $b['team'];
            }
        );
        $accuracyRanks = [];
        foreach (array_slice($accuracyCandidates, 0, 3) as $idx => $row) {
            $accuracyRanks[] = ['team' => (string)$row['team'], 'place' => $idx + 1];
        }

        return [
            'puzzle' => $puzzleRanks,
            'catalog' => $catalogRanks,
            'points' => $pointsRanks,
            'accuracy' => $accuracyRanks,
        ];
    }

    /**
     * Build the congratulation text for a team.
     *
     * @param string $team team name
     * @param array{
     *     puzzle:list<array{team:string,place:int}>,
     *     catalog:list<array{team:string,place:int}>,
     *     points:list<array{team:string,place:int}>,
     *     accuracy:list<array{team:string,place:int}>
     * } $rankings
     * @param array<string,array{title:string,desc:string}>|null $info
     */
    public function buildText(string $team, array $rankings, ?array $info = null): ?string {
        $defaults = [
            'catalog' => [
                'title' => 'Katalogmeister',
                'desc' => 'Team, das alle Kataloge am schnellsten durchgespielt hat',
            ],
            'points' => [
                'title' => 'Highscore-Champions',
                'desc' => 'Team mit den meisten Punkten',
            ],
            'puzzle' => [
                'title' => 'Rätselwort-Bestzeit',
                'desc' => 'schnellstes Lösen des Rätselworts',
            ],
            'accuracy' => [
                'title' => 'Trefferquote-Champions',
                'desc' => 'beste durchschnittliche Effizienz über alle Fragen',
            ],
        ];
        $info = $info ? $info + $defaults : $defaults;

        $lines = [];
        foreach ($rankings as $key => $list) {
            foreach ($list as $entry) {
                if ($entry['team'] === $team) {
                    $place = (int) $entry['place'];
                    $desc = $this->placeDescription($key, $place, $info[$key]['desc']);
                    $lines[] = sprintf('• %s (Platz %d): %s', $info[$key]['title'], $place, $desc);
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
     * Get structured award information for a team.
     *
     * @param string $team team name
     * @param array{
     *     puzzle:list<array{team:string,place:int}>,
     *     catalog:list<array{team:string,place:int}>,
     *     points:list<array{team:string,place:int}>,
     *     accuracy:list<array{team:string,place:int}>
     * } $rankings
     * @param array<string,array{title:string,desc:string}>|null $info
     * @return list<array{place:int,title:string,desc:string}>
     */
    public function getAwards(string $team, array $rankings, ?array $info = null): array {
        $defaults = [
            'catalog' => [
                'title' => 'Katalogmeister',
                'desc' => 'Team, das alle Kataloge am schnellsten durchgespielt hat',
            ],
            'points' => [
                'title' => 'Highscore-Champions',
                'desc' => 'Team mit den meisten Punkten',
            ],
            'puzzle' => [
                'title' => 'Rätselwort-Bestzeit',
                'desc' => 'schnellstes Lösen des Rätselworts',
            ],
            'accuracy' => [
                'title' => 'Trefferquote-Champions',
                'desc' => 'beste durchschnittliche Effizienz über alle Fragen',
            ],
        ];
        $info = $info ? $info + $defaults : $defaults;

        $list = [];
        foreach ($rankings as $key => $entries) {
            foreach ($entries as $entry) {
                if ($entry['team'] === $team) {
                    $place = (int) $entry['place'];
                    $desc = $this->placeDescription($key, $place, $info[$key]['desc']);
                    $list[] = [
                        'place' => $place,
                        'title' => $info[$key]['title'],
                        'desc' => $desc,
                    ];
                }
            }
        }

        return $list;
    }

    private function placeDescription(string $key, int $place, string $default): string {
        return match ($place) {
            2 => match ($key) {
                'puzzle' => 'zweit schnellstes Lösen des Rätselworts',
                'points' => 'zweit bestes Team mit den meisten Punkten',
                'accuracy' => 'zweit beste durchschnittliche Effizienz',
                default => $default,
            },
            3 => match ($key) {
                'puzzle' => 'dritt schnellstes Lösen des Rätselworts',
                'points' => 'dritt bestes Team mit den meisten Punkten',
                'accuracy' => 'dritt beste durchschnittliche Effizienz',
                default => $default,
            },
            default => $default,
        };
    }

    /**
     * Join a list with commas and "und" before the last item.
     *
     * @param list<string> $items
     */
    private function join(array $items): string {
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
