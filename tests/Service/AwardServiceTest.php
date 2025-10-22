<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\AwardService;
use Tests\TestCase;

class AwardServiceTest extends TestCase
{
    public function testSecondPlaceStandaloneSingleCategory(): void {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => [
                ['team' => 'First', 'place' => 1],
                ['team' => 'Team', 'place' => 2],
            ],
            'catalog' => [],
            'points' => [],
        ];

        $text = $svc->buildText('Team', $rankings);
        $expected = "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . "• Rätselwort-Bestzeit (Platz 2): zweit schnellstes Lösen des Rätselworts";
        $this->assertSame($expected, $text);
    }

    public function testSecondPlaceAfterFirstMultipleCategories(): void {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => [
                ['team' => 'A', 'place' => 1],
                ['team' => 'Team', 'place' => 2],
            ],
            'catalog' => [
                ['team' => 'Team', 'place' => 1],
            ],
            'points' => [
                ['team' => 'B', 'place' => 1],
                ['team' => 'Team', 'place' => 2],
            ],
        ];

        $text = $svc->buildText('Team', $rankings);
        $expected = "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . "• Rätselwort-Bestzeit (Platz 2): zweit schnellstes Lösen des Rätselworts\n"
            . "• Katalogmeister (Platz 1): Team, das alle Kataloge am schnellsten durchgespielt hat\n"
            . "• Highscore-Champions (Platz 2): zweit bestes Team mit den meisten Punkten";
        $this->assertSame($expected, $text);
    }

    public function testThirdPlaceOnly(): void {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => [
                ['team' => 'A', 'place' => 1],
                ['team' => 'B', 'place' => 2],
                ['team' => 'Team', 'place' => 3],
            ],
            'catalog' => [],
            'points' => [],
        ];

        $text = $svc->buildText('Team', $rankings);
        $expected = "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . "• Rätselwort-Bestzeit (Platz 3): dritt schnellstes Lösen des Rätselworts";
        $this->assertSame($expected, $text);
    }

    public function testFullCombination(): void {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => [
                ['team' => 'A', 'place' => 1],
                ['team' => 'B', 'place' => 2],
                ['team' => 'Team', 'place' => 3],
            ],
            'catalog' => [
                ['team' => 'Team', 'place' => 1],
            ],
            'points' => [
                ['team' => 'A', 'place' => 1],
                ['team' => 'Team', 'place' => 2],
            ],
        ];

        $expected = "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . "• Rätselwort-Bestzeit (Platz 3): dritt schnellstes Lösen des Rätselworts\n"
            . "• Katalogmeister (Platz 1): Team, das alle Kataloge am schnellsten durchgespielt hat\n"
            . "• Highscore-Champions (Platz 2): zweit bestes Team mit den meisten Punkten";
        $text = $svc->buildText('Team', $rankings);
        $this->assertSame($expected, $text);
    }

    public function testComputeRankingsCalculatesTopTeams(): void {
        $svc = new AwardService();
        $results = [
            ['name' => 'TeamA', 'catalog' => 'cat1', 'time' => 10, 'duration_sec' => 310, 'correct' => 5, 'puzzleTime' => 30, 'attempt' => 1],
            ['name' => 'TeamA', 'catalog' => 'cat2', 'time' => 21, 'duration_sec' => 390, 'correct' => 4, 'attempt' => 1],
            ['name' => 'TeamB', 'catalog' => 'cat1', 'time' => 9, 'duration_sec' => 330, 'correct' => 4, 'puzzleTime' => 35, 'attempt' => 1],
            ['name' => 'TeamB', 'catalog' => 'cat2', 'time' => 19, 'duration_sec' => 410, 'correct' => 3, 'attempt' => 1],
            ['name' => 'TeamC', 'catalog' => 'cat1', 'time' => 14, 'duration_sec' => 360, 'correct' => 3, 'puzzleTime' => 40, 'attempt' => 1],
            ['name' => 'TeamC', 'catalog' => 'cat2', 'time' => 25, 'duration_sec' => 440, 'correct' => 2, 'attempt' => 1],
            ['name' => 'TeamD', 'catalog' => 'cat1', 'time' => 11, 'duration_sec' => 305, 'correct' => 6, 'puzzleTime' => 50, 'attempt' => 1],
        ];
        $questionResults = [
            ['name' => 'TeamA', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 80, 'efficiency' => 0.9],
            ['name' => 'TeamA', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 70, 'efficiency' => 0.8],
            ['name' => 'TeamA', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 30, 'efficiency' => 0.6],
            ['name' => 'TeamA', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 20, 'efficiency' => 0.5],
            ['name' => 'TeamB', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 90, 'efficiency' => 0.4],
            ['name' => 'TeamB', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 60, 'efficiency' => 0.4],
            ['name' => 'TeamB', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 30, 'efficiency' => 0.5],
            ['name' => 'TeamB', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 20, 'efficiency' => 0.5],
            ['name' => 'TeamC', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 70, 'efficiency' => 0.7],
            ['name' => 'TeamC', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 40, 'efficiency' => 0.6],
            ['name' => 'TeamD', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 60, 'efficiency' => 0.9],
        ];
        $rankings = $svc->computeRankings($results, null, $questionResults);

        $this->assertSame(
            [
                ['team' => 'TeamA', 'place' => 1],
                ['team' => 'TeamB', 'place' => 2],
                ['team' => 'TeamC', 'place' => 3],
            ],
            $rankings['puzzle']
        );

        $this->assertSame(
            [
                ['team' => 'TeamA', 'place' => 1],
                ['team' => 'TeamB', 'place' => 2],
                ['team' => 'TeamC', 'place' => 3],
            ],
            $rankings['catalog']
        );

        $this->assertSame(
            [
                ['team' => 'TeamA', 'place' => 1],
                ['team' => 'TeamB', 'place' => 2],
                ['team' => 'TeamC', 'place' => 3],
            ],
            $rankings['points']
        );

        $this->assertSame(
            [
                ['team' => 'TeamD', 'place' => 1],
                ['team' => 'TeamA', 'place' => 2],
                ['team' => 'TeamC', 'place' => 3],
            ],
            $rankings['accuracy']
        );
    }

    public function testPointsRankingUsesEfficiencyTieBreak(): void {
        $svc = new AwardService();
        $results = [
            ['name' => 'Fast', 'catalog' => 'cat1', 'time' => 10, 'duration_sec' => 300, 'attempt' => 1],
            ['name' => 'Fast', 'catalog' => 'cat2', 'time' => 20, 'duration_sec' => 320, 'attempt' => 1],
            ['name' => 'Slow', 'catalog' => 'cat1', 'time' => 12, 'duration_sec' => 360, 'attempt' => 1],
            ['name' => 'Slow', 'catalog' => 'cat2', 'time' => 22, 'duration_sec' => 380, 'attempt' => 1],
        ];
        $questionResults = [
            ['name' => 'Fast', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 50, 'efficiency' => 0.9],
            ['name' => 'Fast', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 50, 'efficiency' => 0.9],
            ['name' => 'Fast', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 50, 'efficiency' => 0.8],
            ['name' => 'Fast', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 50, 'efficiency' => 0.8],
            ['name' => 'Slow', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 60, 'efficiency' => 0.5],
            ['name' => 'Slow', 'catalog' => 'cat1', 'attempt' => 1, 'final_points' => 60, 'efficiency' => 0.5],
            ['name' => 'Slow', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 40, 'efficiency' => 0.4],
            ['name' => 'Slow', 'catalog' => 'cat2', 'attempt' => 1, 'final_points' => 40, 'efficiency' => 0.4],
        ];

        $rankings = $svc->computeRankings($results, null, $questionResults);

        $this->assertSame(
            [
                ['team' => 'Fast', 'place' => 1],
                ['team' => 'Slow', 'place' => 2],
            ],
            $rankings['points']
        );

        $this->assertSame(
            [
                ['team' => 'Fast', 'place' => 1],
                ['team' => 'Slow', 'place' => 2],
            ],
            $rankings['accuracy']
        );
    }

    public function testNegativeFinalPointsReduceTotalsAndClampEfficiency(): void {
        $svc = new AwardService();
        $results = [
            ['name' => 'Neutral', 'catalog' => 'main', 'time' => 15, 'duration_sec' => 320, 'correct' => 4, 'attempt' => 1],
            ['name' => 'Penalty', 'catalog' => 'main', 'time' => 18, 'duration_sec' => 340, 'correct' => 3, 'attempt' => 1],
            ['name' => 'Bonus', 'catalog' => 'main', 'time' => 16, 'duration_sec' => 330, 'correct' => 3, 'attempt' => 1],
        ];
        $questionResults = [
            ['name' => 'Neutral', 'catalog' => 'main', 'attempt' => 1, 'final_points' => 40, 'efficiency' => 0.8],
            ['name' => 'Neutral', 'catalog' => 'main', 'attempt' => 1, 'final_points' => 30, 'efficiency' => 1.3],
            ['name' => 'Penalty', 'catalog' => 'main', 'attempt' => 1, 'final_points' => 60, 'efficiency' => 0.7],
            ['name' => 'Penalty', 'catalog' => 'main', 'attempt' => 1, 'final_points' => -120, 'efficiency' => -0.4],
            ['name' => 'Bonus', 'catalog' => 'main', 'attempt' => 1, 'final_points' => 25, 'efficiency' => 0.5],
            ['name' => 'Bonus', 'catalog' => 'main', 'attempt' => 1, 'final_points' => 15, 'efficiency' => 0.6],
        ];

        $rankings = $svc->computeRankings($results, null, $questionResults);

        $this->assertSame(
            [
                ['team' => 'Neutral', 'place' => 1],
                ['team' => 'Bonus', 'place' => 2],
                ['team' => 'Penalty', 'place' => 3],
            ],
            $rankings['points']
        );

        $this->assertSame(
            [
                ['team' => 'Neutral', 'place' => 1],
                ['team' => 'Bonus', 'place' => 2],
                ['team' => 'Penalty', 'place' => 3],
            ],
            $rankings['accuracy']
        );
    }

    public function testGetAwardsListsDescriptions(): void {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => [
                ['team' => 'Team', 'place' => 2],
            ],
            'catalog' => [],
            'points' => [
                ['team' => 'Team', 'place' => 1],
            ],
        ];
        $awards = $svc->getAwards('Team', $rankings);
        $this->assertSame(
            [
                [
                    'place' => 2,
                    'title' => 'Rätselwort-Bestzeit',
                    'desc' => 'zweit schnellstes Lösen des Rätselworts',
                ],
                [
                    'place' => 1,
                    'title' => 'Highscore-Champions',
                    'desc' => 'Team mit den meisten Punkten',
                ],
            ],
            $awards
        );
    }
}
