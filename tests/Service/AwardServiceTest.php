<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\AwardService;
use Tests\TestCase;

class AwardServiceTest extends TestCase
{
    public function testSecondPlaceStandaloneSingleCategory(): void
    {
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
            . "• Rätselwort-Bestzeit (Platz 2): schnellstes Lösen des Rätselworts";
        $this->assertSame($expected, $text);
    }

    public function testSecondPlaceAfterFirstMultipleCategories(): void
    {
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
            . "• Rätselwort-Bestzeit (Platz 2): schnellstes Lösen des Rätselworts\n"
            . "• Katalogmeister (Platz 1): Team, das alle Kataloge am schnellsten durchgespielt hat\n"
            . "• Highscore-Champions (Platz 2): Team mit den meisten Lösungen aller Fragen";
        $this->assertSame($expected, $text);
    }

    public function testThirdPlaceOnly(): void
    {
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
            . "• Rätselwort-Bestzeit (Platz 3): schnellstes Lösen des Rätselworts";
        $this->assertSame($expected, $text);
    }

    public function testFullCombination(): void
    {
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
            . "• Rätselwort-Bestzeit (Platz 3): schnellstes Lösen des Rätselworts\n"
            . "• Katalogmeister (Platz 1): Team, das alle Kataloge am schnellsten durchgespielt hat\n"
            . "• Highscore-Champions (Platz 2): Team mit den meisten Lösungen aller Fragen";
        $text = $svc->buildText('Team', $rankings);
        $this->assertSame($expected, $text);
    }
}
