<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\TranslationService;
use App\Twig\DateTimeFormatExtension;
use App\Twig\TranslationExtension;
use App\Twig\UikitExtension;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;

final class MarketingWikiThemeRenderTest extends TestCase
{
    public function testThemeDataRenderedIntoMarkup(): void
    {
        $twig = Twig::create(__DIR__ . '/../../templates', ['cache' => false]);
        $twig->addExtension(new UikitExtension());
        $twig->addExtension(new DateTimeFormatExtension());
        $twig->addExtension(new TranslationExtension(new TranslationService()));
        $twig->getEnvironment()->addGlobal('basePath', '');
        $twig->getEnvironment()->addGlobal('baseUrl', '');
        $twig->getEnvironment()->addGlobal('config', []);

        $page = new class {
            public function getSlug(): string
            {
                return 'page';
            }

            public function getTitle(): string
            {
                return 'Demo Page';
            }
        };

        $article = new class {
            public function getSlug(): string
            {
                return 'article';
            }

            public function getTitle(): string
            {
                return 'Article Title';
            }

            public function getExcerpt(): string
            {
                return 'Excerpt';
            }

            public function getUpdatedAt(): DateTimeImmutable
            {
                return new DateTimeImmutable('2024-01-01T12:00:00Z');
            }

            public function getPublishedAt(): ?DateTimeImmutable
            {
                return null;
            }
        };

        $theme = [
            'bodyClasses' => ['marketing-wiki', 'custom-theme'],
            'stylesheets' => ['custom.css', 'https://cdn.example.com/theme.css'],
            'colors' => [
                'headerFrom' => '#101010',
                'headerTo' => '#202020',
            ],
            'logoUrl' => 'https://example.com/logo.svg',
        ];

        $html = $twig->getEnvironment()->render('marketing/wiki/index.twig', [
            'basePath' => '',
            'page' => $page,
            'articles' => [$article],
            'searchTerm' => '',
            'menuLabel' => 'Docs',
            'wikiTheme' => $theme,
            'breadcrumbs' => [
                ['url' => '/page', 'label' => 'Page'],
                ['url' => '/pages/page/wiki', 'label' => 'Docs'],
            ],
        ]);

        self::assertStringContainsString('class="marketing-wiki custom-theme"', $html);
        self::assertStringContainsString('href="/css/custom.css"', $html);
        self::assertStringContainsString('href="https://cdn.example.com/theme.css"', $html);
        self::assertStringContainsString('--marketing-wiki-header-from: #101010', $html);
        self::assertStringContainsString('src="https://example.com/logo.svg"', $html);
    }
}
