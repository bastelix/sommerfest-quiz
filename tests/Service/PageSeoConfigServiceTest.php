<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Application\Seo\PageSeoConfigService;
use App\Application\Seo\SeoValidator;
use App\Domain\PageSeoConfig;
use App\Infrastructure\Cache\PageSeoCache;
use PHPUnit\Framework\TestCase;

class PageSeoConfigServiceTest extends TestCase
{
    public function testValidateLimitsAndUrl(): void
    {
        $service = new PageSeoConfigService();
        $errors = $service->validate([
            'slug' => 'test',
            'metaTitle' => str_repeat('a', SeoValidator::TITLE_MAX_LENGTH + 1),
            'metaDescription' => str_repeat('b', SeoValidator::DESCRIPTION_MAX_LENGTH + 1),
            'canonicalUrl' => 'not-a-url',
        ]);
        $this->assertArrayHasKey('metaTitle', $errors);
        $this->assertArrayHasKey('metaDescription', $errors);
        $this->assertArrayHasKey('canonicalUrl', $errors);
    }

    public function testCacheInvalidatedOnSave(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'seo');
        $cache = new PageSeoCache();
        $service = new PageSeoConfigService($file, null, null, $cache);
        $config = new PageSeoConfig(1, 'start');
        $service->save($config);
        $first = $service->load(1);
        $this->assertSame('start', $first->getSlug());
        $service->save(new PageSeoConfig(1, 'changed'));
        $second = $service->load(1);
        $this->assertSame('changed', $second->getSlug());
        unlink($file);
    }

    public function testSlugAllowsSlashesAndUnderscores(): void
    {
        $service = new PageSeoConfigService();
        $valid = $service->validate(['slug' => 'foo/bar_baz-1']);
        $this->assertArrayNotHasKey('slug', $valid);
        $invalid = $service->validate(['slug' => 'Foo']);
        $this->assertArrayHasKey('slug', $invalid);
    }
}
