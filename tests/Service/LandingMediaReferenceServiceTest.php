<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Domain\PageSeoConfig;
use App\Service\LandingMediaReferenceService;
use App\Service\PageService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LandingMediaReferenceServiceTest extends TestCase
{
    /** @var list<Page> */
    private array $pages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pages = [];
    }

    public function testCollectsReferencesAndMarksMissingFiles(): void
    {
        $uploadsRoot = sys_get_temp_dir() . '/landing-media-' . uniqid('', true);
        if (!is_dir($uploadsRoot)) {
            mkdir($uploadsRoot, 0777, true);
        }

        file_put_contents($uploadsRoot . '/hero.png', 'png');
        file_put_contents($uploadsRoot . '/hero.webp', 'webp');
        file_put_contents($uploadsRoot . '/og.png', 'seo');

        $content = <<<'HTML'
<img src="/uploads/hero.png" alt="">
<source srcset="https://cdn.example.com/uploads/hero.avif 1x, /uploads/hero.webp 2x">
<video poster="/uploads/trailer.jpg"></video>
HTML;
        $landingPage = new Page(1, 'landing', 'Landing', $content);
        $promoPage = new Page(2, 'promo', 'Promo', '<img src="/uploads/promo.png" alt="Promo">');
        $this->pages = [$landingPage, $promoPage, new Page(3, 'impressum', 'Imprint', '')];

        $pageService = new class($this->pages) extends PageService {
            /** @var list<Page> */
            private array $pages;

            public function __construct(array $pages)
            {
                $this->pages = $pages;
            }

            public function getAll(): array
            {
                return $this->pages;
            }

            public function findBySlug(string $slug): ?Page
            {
                foreach ($this->pages as $page) {
                    if ($page->getSlug() === $slug) {
                        return $page;
                    }
                }

                return null;
            }
        };

        $config = new PageSeoConfig(
            1,
            'landing',
            ogImage: '/uploads/og.png',
            faviconPath: '/uploads/favicon.ico'
        );

        $seoService = new class($config) extends PageSeoConfigService {
            private PageSeoConfig $config;

            public function __construct(PageSeoConfig $config)
            {
                $this->config = $config;
            }

            public function load(int $pageId): ?PageSeoConfig
            {
                return $pageId === $this->config->getPageId() ? $this->config : null;
            }
        };

        $service = new LandingMediaReferenceService(
            $pageService,
            $seoService,
            '/uploads',
            $uploadsRoot
        );

        $slugs = $service->getAvailableSlugs();
        $this->assertSame(['landing', 'promo'], $slugs);

        $references = $service->getReferences('landing');
        $paths = array_column($references, 'path');
        $this->assertTrue(in_array('/uploads/hero.png', $paths, true));
        $this->assertTrue(in_array('/uploads/hero.avif', $paths, true));
        $this->assertTrue(in_array('/uploads/hero.webp', $paths, true));
        $this->assertTrue(in_array('/uploads/trailer.jpg', $paths, true));
        $this->assertTrue(in_array('/uploads/og.png', $paths, true));
        $this->assertTrue(in_array('/uploads/favicon.ico', $paths, true));

        $indexed = [];
        foreach ($references as $entry) {
            $indexed[$entry['path']] = $entry;
        }

        $this->assertFalse($indexed['/uploads/hero.png']['missing']);
        $this->assertFalse($indexed['/uploads/hero.webp']['missing']);
        $this->assertFalse($indexed['/uploads/og.png']['missing']);
        $this->assertTrue($indexed['/uploads/hero.avif']['missing']);
        $this->assertTrue($indexed['/uploads/trailer.jpg']['missing']);
        $this->assertTrue($indexed['/uploads/favicon.ico']['missing']);

        $this->expectException(InvalidArgumentException::class);
        $service->getReferences('impressum');
    }
}
