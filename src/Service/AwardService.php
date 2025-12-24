<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\TimestampHelper;

/**
 * Calculate rankings from result data and build congratulation texts.
 */
class AwardService
{
    private const BLANK_TEAM_KEY = '__blank__';
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
        $puzzleTimes = [];
        $scores = [];
        $attemptMetrics = [];

        foreach ($questionResults as $row) {
            $team = (string)($row['name'] ?? '');
            $catalogKey = $this->normalizeCatalogKey($row);
            if ($team === '' || $catalogKey === '') {
                continue;
            }
            $attempt = (int)($row['attempt'] ?? 1);
            $key = $team . '|' . $catalogKey . '|' . $attempt;
            $finalPointsRaw = $row['final_points'] ?? $row['finalPoints'] ?? $row['points'] ?? 0;
            $finalPoints = (int) $finalPointsRaw;
            $efficiencyRaw = $row['efficiency'] ?? null;
            $efficiency = $efficiencyRaw !== null ? (float) $efficiencyRaw : ((int)($row['correct'] ?? 0) === 1 ? 1.0 : 0.0);
            $correctRaw = $row['is_correct'] ?? $row['isCorrect'] ?? $row['correct'] ?? 0;
            $correctValue = 0;
            if (is_bool($correctRaw)) {
                $correctValue = $correctRaw ? 1 : 0;
            } elseif (is_numeric($correctRaw)) {
                $parsed = (int) round((float) $correctRaw);
                if ($parsed > 0) {
                    $correctValue = $parsed;
                }
            } elseif (is_string($correctRaw)) {
                $normalized = strtolower(trim($correctRaw));
                if ($normalized === 'true' || $normalized === 'yes' || $normalized === 'y') {
                    $correctValue = 1;
                } elseif (is_numeric($normalized)) {
                    $parsed = (int) round((float) $normalized);
                    if ($parsed > 0) {
                        $correctValue = $parsed;
                    }
                }
            }
            if ($efficiency < 0.0) {
                $efficiency = 0.0;
            } elseif ($efficiency > 1.0) {
                $efficiency = 1.0;
            }
            if (!isset($attemptMetrics[$key])) {
                $attemptMetrics[$key] = [
                    'points' => 0,
                    'efficiencySum' => 0.0,
                    'questionCount' => 0,
                    'correctCount' => 0,
                ];
            }
            $attemptMetrics[$key]['points'] += $finalPoints;
            $attemptMetrics[$key]['efficiencySum'] += $efficiency;
            $attemptMetrics[$key]['questionCount']++;
            $attemptMetrics[$key]['correctCount'] += $correctValue;
        }

        foreach ($results as $row) {
            $team = (string)($row['name'] ?? '');
            $catalogKey = $this->normalizeCatalogKey($row);
            if ($team === '' || $catalogKey === '') {
                continue;
            }
            $time = (int)($row['time'] ?? 0);
            $correct = (int)($row['correct'] ?? 0);
            $points = isset($row['points']) ? (int)$row['points'] : $correct;
            $puzzle = isset($row['puzzleTime']) ? (int)$row['puzzleTime'] : null;
            $attempt = (int)($row['attempt'] ?? 1);
            $key = $team . '|' . $catalogKey . '|' . $attempt;
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
            $solved = 0;
            if ($summary !== null) {
                $finalPoints = (int) $summary['points'];
                $effSum = (float) $summary['efficiencySum'];
                $questionCount = max(0, (int) $summary['questionCount']);
                $solved = (int) $summary['correctCount'];
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
                    if ($avgFallback < 0.0) {
                        $avgFallback = 0.0;
                    } elseif ($avgFallback > 1.0) {
                        $avgFallback = 1.0;
                    }
                    $effSum = $avgFallback * $questionCount;
                }
            }
            $average = $questionCount === 0 ? 0.0 : $effSum / $questionCount;
            if ($average < 0.0) {
                $average = 0.0;
            } elseif ($average > 1.0) {
                $average = 1.0;
            }

            if ($puzzle !== null) {
                if (!isset($puzzleTimes[$team]) || $puzzle < $puzzleTimes[$team]) {
                    $puzzleTimes[$team] = $puzzle;
                }
            }

            if ($summary === null) {
                $solvedRaw = $row['correct'] ?? 0;
                if (is_numeric($solvedRaw)) {
                    $solved = (int) round((float) $solvedRaw);
                } elseif (is_bool($solvedRaw)) {
                    $solved = $solvedRaw ? 1 : 0;
                } else {
                    $solved = 0;
                }
                if ($solved < 0) {
                    $solved = 0;
                }
            }

            $existing = $scores[$team][$catalogKey] ?? null;
            $shouldReplace = false;
            if ($existing === null) {
                $shouldReplace = true;
            } else {
                $existingSolved = (int) $existing['solved'];
                $existingPoints = (int) $existing['points'];
                $existingDuration = $existing['duration'];
                $existingAverage = (float) $existing['average'];
                $existingFinish = (int) $existing['finish'];

                if ($solved > $existingSolved) {
                    $shouldReplace = true;
                } elseif ($solved === $existingSolved) {
                    if ($finalPoints > $existingPoints) {
                        $shouldReplace = true;
                    } elseif ($finalPoints === $existingPoints) {
                        $durationCmp = 0;
                        $hasDuration = $duration !== null;
                        $existingHasDuration = $existingDuration !== null;
                        if ($hasDuration && $existingHasDuration) {
                            $durationCmp = $duration <=> (int) $existingDuration;
                        } elseif ($hasDuration) {
                            $durationCmp = -1;
                        } elseif ($existingHasDuration) {
                            $durationCmp = 1;
                        }

                        if ($durationCmp < 0) {
                            $shouldReplace = true;
                        } elseif ($durationCmp === 0) {
                            if ($average > $existingAverage) {
                                $shouldReplace = true;
                            } elseif ($average === $existingAverage && $time < $existingFinish) {
                                $shouldReplace = true;
                            }
                        }
                    }
                }
            }

            if ($shouldReplace) {
                $scores[$team][$catalogKey] = [
                    'points' => $finalPoints,
                    'average' => $average,
                    'efficiencySum' => $effSum,
                    'questionCount' => $questionCount,
                    'solved' => $solved,
                    'duration' => $duration,
                    'finish' => $time,
                ];
            }
        }

        $puzzleList = [];
        foreach ($puzzleTimes as $team => $t) {
            $puzzleList[] = ['team' => $team, 'time' => $t];
        }
        usort($puzzleList, fn($a, $b) => $a['time'] <=> $b['time']);
        $puzzleRanks = [];
        $seenPuzzleTeams = [];
        foreach ($puzzleList as $row) {
            $teamName = (string) $row['team'];
            $key = $this->normalizeTeamKey($teamName);
            $key = $key === '' ? self::BLANK_TEAM_KEY : $key;
            if (isset($seenPuzzleTeams[$key])) {
                continue;
            }
            $seenPuzzleTeams[$key] = true;
            $puzzleRanks[] = [
                'team' => $this->sanitizeTeamDisplay($teamName),
                'place' => count($puzzleRanks) + 1,
            ];
            if (count($puzzleRanks) >= 3) {
                break;
            }
        }

        $catalogCandidates = [];

        $scoreList = [];
        $accuracyCandidates = [];
        foreach ($scores as $team => $map) {
            $displayTeam = $this->sanitizeTeamDisplay((string) $team);
            $total = 0;
            $effSumTotal = 0.0;
            $questionCountTotal = 0;
            $totalSolved = 0;
            $totalDuration = 0;
            $durationEntries = 0;
            $latestFinish = null;
            $latestFinishValue = null;
            foreach ($map as $entry) {
                $total += (int) $entry['points'];
                $effSumTotal += (float) $entry['efficiencySum'];
                $questionCountTotal += (int) $entry['questionCount'];
                $totalSolved += (int) $entry['solved'];
                $durationValue = $entry['duration'];
                if ($durationValue !== null) {
                    $totalDuration += (int) $durationValue;
                    $durationEntries++;
                }
                $finishRaw = $entry['finish'];
                $preparedFinish = TimestampHelper::normalize($finishRaw);
                if ($preparedFinish !== null && ($latestFinishValue === null || $preparedFinish > $latestFinishValue)) {
                    $latestFinishValue = $preparedFinish;
                    $latestFinish = $finishRaw;
                }
            }
            $avgEfficiency = $questionCountTotal === 0 ? 0.0 : $effSumTotal / $questionCountTotal;
            if ($avgEfficiency < 0.0) {
                $avgEfficiency = 0.0;
            } elseif ($avgEfficiency > 1.0) {
                $avgEfficiency = 1.0;
            }
            $scoreList[] = ['team' => $displayTeam, 'score' => $total, 'avgEfficiency' => $avgEfficiency];
            if ($questionCountTotal > 0) {
                $accuracyCandidates[] = [
                    'team' => $displayTeam,
                    'avgEfficiency' => $avgEfficiency,
                    'questionCount' => $questionCountTotal,
                    'score' => $total,
                ];
            }
            $catalogCandidates[] = [
                'team' => $displayTeam,
                'solved' => $totalSolved,
                'points' => $total,
                'duration' => $durationEntries > 0 ? $totalDuration : null,
                'latestFinish' => $latestFinish,
                'latestFinishValue' => $latestFinishValue,
            ];
        }
        usort(
            $catalogCandidates,
            /**
             * @param array{
             *     team:mixed,
             *     solved:int,
             *     points:int,
             *     duration:int|null,
             *     latestFinish:mixed,
             *     latestFinishValue:int|null
             * } $a
             * @param array{
             *     team:mixed,
             *     solved:int,
             *     points:int,
             *     duration:int|null,
             *     latestFinish:mixed,
             *     latestFinishValue:int|null
             * } $b
             */
            static function (array $a, array $b): int {
                $cmp = $b['solved'] <=> $a['solved'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                  $cmp = $b['points'] <=> $a['points'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                  $aDuration = $a['duration'];
                  $bDuration = $b['duration'];
                  $aHasDuration = $aDuration !== null;
                  $bHasDuration = $bDuration !== null;
                if ($aHasDuration && $bHasDuration) {
                    $cmp = $aDuration <=> $bDuration;
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                } elseif ($aHasDuration) {
                    return -1;
                } elseif ($bHasDuration) {
                    return 1;
                }

                $aFinishValue = $a['latestFinishValue'];
                $bFinishValue = $b['latestFinishValue'];
                if ($aFinishValue === null) {
                    $aFinishValue = PHP_INT_MAX;
                }
                if ($bFinishValue === null) {
                    $bFinishValue = PHP_INT_MAX;
                }
                $cmp = $aFinishValue <=> $bFinishValue;
                if ($cmp !== 0) {
                    return $cmp;
                }

                  return $a['team'] <=> $b['team'];
            }
        );

        $catalogRanks = [];
        $seenCatalogTeams = [];
        foreach ($catalogCandidates as $row) {
            $teamName = (string) $row['team'];
            $key = $this->normalizeTeamKey($teamName);
            $key = $key === '' ? self::BLANK_TEAM_KEY : $key;
            if (isset($seenCatalogTeams[$key])) {
                continue;
            }
            $seenCatalogTeams[$key] = true;
            $catalogRanks[] = [
                'team' => $this->sanitizeTeamDisplay($teamName),
                'place' => count($catalogRanks) + 1,
                'solved' => (int) $row['solved'],
                'points' => (int) $row['points'],
                'duration' => $row['duration'] !== null ? (int) $row['duration'] : null,
                'finished' => $row['latestFinish'],
            ];
            if (count($catalogRanks) >= 3) {
                break;
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
        $seenPointTeams = [];
        foreach ($scoreList as $row) {
            $teamName = (string) $row['team'];
            $key = $this->normalizeTeamKey($teamName);
            $key = $key === '' ? self::BLANK_TEAM_KEY : $key;
            if (isset($seenPointTeams[$key])) {
                continue;
            }
            $seenPointTeams[$key] = true;
            $pointsRanks[] = [
                'team' => $this->sanitizeTeamDisplay($teamName),
                'place' => count($pointsRanks) + 1,
            ];
            if (count($pointsRanks) >= 3) {
                break;
            }
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
        $seenAccuracyTeams = [];
        foreach ($accuracyCandidates as $row) {
            $teamName = (string) $row['team'];
            $key = $this->normalizeTeamKey($teamName);
            $key = $key === '' ? self::BLANK_TEAM_KEY : $key;
            if (isset($seenAccuracyTeams[$key])) {
                continue;
            }
            $seenAccuracyTeams[$key] = true;
            $accuracyRanks[] = [
                'team' => $this->sanitizeTeamDisplay($teamName),
                'place' => count($accuracyRanks) + 1,
            ];
            if (count($accuracyRanks) >= 3) {
                break;
            }
        }

        return [
            'puzzle' => $puzzleRanks,
            'catalog' => $catalogRanks,
            'points' => $pointsRanks,
            'accuracy' => $accuracyRanks,
        ];
    }

    private function normalizeTeamKey(string $team): string
    {
        $trimmed = trim($team);
        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $lower = mb_strtolower($trimmed, 'UTF-8');
        } else {
            $lower = strtolower($trimmed);
        }

        return (string) preg_replace('/\s+/u', ' ', $lower);
    }

    private function sanitizeTeamDisplay(string $team): string
    {
        return trim($team);
    }

    private function normalizeCatalogKey(array $row): string
    {
        $candidates = [
            $row['catalogUid'] ?? null,
            $row['catalog_uid'] ?? null,
            $row['catalogRef'] ?? null,
            $row['catalogKey'] ?? null,
            $row['catalog_slug'] ?? null,
            $row['catalog'] ?? null,
        ];

        foreach ($candidates as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
                continue;
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return '';
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
                'title' => 'Ranking-Champions',
                'desc' => 'Team mit den meisten gelösten Fragen (Tie-Breaker: Punkte, Gesamtzeit)',
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
                'title' => 'Ranking-Champions',
                'desc' => 'Team mit den meisten gelösten Fragen (Tie-Breaker: Punkte, Gesamtzeit)',
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
                'catalog' => 'zweithöchste Zahl gelöster Fragen',
                default => $default,
            },
            3 => match ($key) {
                'puzzle' => 'dritt schnellstes Lösen des Rätselworts',
                'points' => 'dritt bestes Team mit den meisten Punkten',
                'accuracy' => 'dritt beste durchschnittliche Effizienz',
                'catalog' => 'dritthöchste Zahl gelöster Fragen',
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
