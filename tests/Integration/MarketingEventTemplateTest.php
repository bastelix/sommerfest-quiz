<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\TranslationService;
use App\Twig\DateTimeFormatExtension;
use App\Twig\TranslationExtension;
use App\Twig\UikitExtension;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class MarketingEventTemplateTest extends TestCase
{
    private function createTwig(): Environment
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $twig = new Environment($loader, ['cache' => false]);
        $translator = new TranslationService('de');
        $twig->addExtension(new UikitExtension());
        $twig->addExtension(new DateTimeFormatExtension());
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addGlobal('basePath', '');

        return $twig;
    }

    public function testUpcomingEventTemplateUsesGermanDateFormatting(): void
    {
        $twig = $this->createTwig();
        $start = new DateTimeImmutable('2025-10-05 18:00:00', new DateTimeZone('Europe/Berlin'));
        $end = new DateTimeImmutable('2025-10-05 20:30:00', new DateTimeZone('Europe/Berlin'));

        $html = $twig->render('marketing/event_upcoming.twig', [
            'event' => [
                'name' => 'QuizRace Herbstfest',
                'description' => '',
            ],
            'config' => [],
            'start' => $start,
            'end' => $end,
            'baseUrl' => 'https://example.com',
        ]);

        $this->assertStringContainsString('05.10.2025 um 18:00 Uhr', $html);
        $this->assertStringContainsString('05.10.2025 um 20:30 Uhr', $html);
        $this->assertMatchesRegularExpression('/data-start="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})"/', $html);
    }
}
