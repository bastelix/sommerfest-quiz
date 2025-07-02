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
            'puzzle' => ['First', 'Team'],
            'catalog' => [],
            'points' => [],
        ];

        $text = $svc->buildText('Team', $rankings);
        $expected = "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . "• Rätselwort-Bestzeit: schnellstes Lösen des Rätselworts";
        $this->assertSame($expected, $text);
    }

    public function testSecondPlaceAfterFirstMultipleCategories(): void
    {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => ['A', 'Team'],
            'catalog' => ['Team'],
            'points' => ['B', 'Team'],
        ];

        $text = $svc->buildText('Team', $rankings);
        $expected = "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . "• Rätselwort-Bestzeit: schnellstes Lösen des Rätselworts\n"
            . "• Katalogmeister: Team, das alle Kataloge am schnellsten durchgespielt hat\n"
            . "• Highscore-Champions: Team mit den meisten Lösungen aller Fragen";
        $this->assertSame($expected, $text);
    }

    public function testThirdPlaceOnly(): void
    {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => ['A', 'B', 'Team'],
            'catalog' => [],
            'points' => [],
        ];

        $text = $svc->buildText('Team', $rankings);
        $expected = "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . "• Rätselwort-Bestzeit: schnellstes Lösen des Rätselworts";
        $this->assertSame($expected, $text);
    }

    public function testFullCombination(): void
    {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => ['A', 'B', 'Team'],
            'catalog' => ['Team'],
            'points' => ['A', 'Team'],
        ];

        $expected = "Herzlichen Glückwunsch! Ihr habt folgende Auszeichnungen erreicht:\n"
            . "• Rätselwort-Bestzeit: schnellstes Lösen des Rätselworts\n"
            . "• Katalogmeister: Team, das alle Kataloge am schnellsten durchgespielt hat\n"
            . "• Highscore-Champions: Team mit den meisten Lösungen aller Fragen";
        $text = $svc->buildText('Team', $rankings);
        $this->assertSame($expected, $text);
    }
}
