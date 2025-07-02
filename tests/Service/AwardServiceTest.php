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
        $this->assertSame(
            'In der Kategorie Rätselwort-Bestzeit habt ihr einen tollen zweiten Platz erreicht.',
            $text
        );
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
        $expected = 'Herzlichen Glückwunsch! Ihr seid Katalogmeister – Team, '
            . 'das alle Kataloge am schnellsten durchgespielt hat. '
            . 'Auch in den Kategorien Rätselwort-Bestzeit und Highscore-Champions '
            . 'habt ihr einen tollen zweiten Platz erreicht.';
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
        $this->assertSame('In Rätselwort-Bestzeit wart ihr unter den Top 3!', $text);
    }

    public function testFullCombination(): void
    {
        $svc = new AwardService();
        $rankings = [
            'puzzle' => ['A', 'B', 'Team'],
            'catalog' => ['Team'],
            'points' => ['A', 'Team'],
        ];

        $expected = 'Herzlichen Glückwunsch! Ihr seid Katalogmeister – Team, '
            . 'das alle Kataloge am schnellsten durchgespielt hat. '
            . 'Auch in der Kategorie Highscore-Champions habt ihr einen tollen zweiten Platz erreicht. '
            . 'Und in Rätselwort-Bestzeit wart ihr unter den Top 3!';
        $text = $svc->buildText('Team', $rankings);
        $this->assertSame($expected, $text);
    }
}
