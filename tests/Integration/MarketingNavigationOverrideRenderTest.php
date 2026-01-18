<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\TranslationService;
use App\Twig\DateTimeFormatExtension;
use App\Twig\TranslationExtension;
use App\Twig\UikitExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class MarketingNavigationOverrideRenderTest extends TestCase
{
    public function testHeaderAndFooterRenderOverrideMenus(): void
    {
        $twig = $this->createTwig();

        $html = $twig->render('pages/render.twig', [
            'pageNamespace' => 'tenant',
            'contentNamespace' => 'tenant',
            'cmsSlug' => 'landing',
            'pageTitle' => 'Landing',
            'content' => '<p>Content</p>',
            'menu' => [],
            'cmsMenuItems' => [],
            'cmsMainNavigation' => [
                [
                    'label' => 'Header Override',
                    'href' => '/override',
                    'isExternal' => false,
                    'children' => [],
                ],
            ],
            'cmsFooterColumns' => [
                [
                    'slot' => 'footer_col_1',
                    'items' => [
                        [
                            'label' => 'Footer Override',
                            'href' => '/footer',
                            'isExternal' => false,
                            'children' => [],
                        ],
                    ],
                ],
            ],
            'cmsLegalNavigation' => [
                [
                    'label' => 'Legal Override',
                    'href' => '/legal',
                    'isExternal' => false,
                    'children' => [],
                ],
            ],
            'design' => [
                'theme' => 'light',
                'appearance' => [],
            ],
            'renderContext' => [
                'design' => [
                    'appearance' => [],
                ],
            ],
            'pageJson' => [
                'namespace' => 'tenant',
                'contentNamespace' => 'tenant',
            ],
        ]);

        $this->assertStringContainsString('Header Override', $html);
        $this->assertStringContainsString('Footer Override', $html);
        $this->assertStringContainsString('Legal Override', $html);
    }

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
}
