<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Application\Seo\PageSeoConfigService;
use App\Application\Seo\SeoValidator;
use App\Domain\PageSeoConfig;
use App\Infrastructure\Cache\PageSeoCache;
use PDO;
use PHPUnit\Framework\TestCase;

class PageSeoConfigServiceTest extends TestCase
{
    public function testValidateLimitsAndUrl(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $service = new PageSeoConfigService($pdo);
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
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE page_seo_config(page_id INTEGER PRIMARY KEY, meta_title TEXT, meta_description TEXT, slug TEXT UNIQUE NOT NULL, canonical_url TEXT, robots_meta TEXT, og_title TEXT, og_description TEXT, og_image TEXT, schema_json TEXT, hreflang TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE page_seo_config_history(id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER, meta_title TEXT, meta_description TEXT, slug TEXT, canonical_url TEXT, robots_meta TEXT, og_title TEXT, og_description TEXT, og_image TEXT, schema_json TEXT, hreflang TEXT, created_at TEXT)');
        $file = tempnam(sys_get_temp_dir(), 'seo');
        $cache = new PageSeoCache();
        $service = new PageSeoConfigService($pdo, $file, null, null, $cache);
        $config = new PageSeoConfig(1, 'start');
        $service->save($config);
        $first = $service->load(1);
        $this->assertSame('start', $first->getSlug());
        $service->save(new PageSeoConfig(1, 'changed'));
        $second = $service->load(1);
        $this->assertSame('changed', $second->getSlug());
        $row = $pdo->query('SELECT slug FROM page_seo_config WHERE page_id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('changed', $row['slug']);
        unlink($file);
    }

    public function testSlugAllowsSlashesAndUnderscores(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $service = new PageSeoConfigService($pdo);
        $valid = $service->validate(['slug' => 'foo/bar_baz-1']);
        $this->assertArrayNotHasKey('slug', $valid);
        $invalid = $service->validate(['slug' => 'Foo']);
        $this->assertArrayHasKey('slug', $invalid);
    }

    public function testEmptySchemaJsonSavedAsNull(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE page_seo_config(page_id INTEGER PRIMARY KEY, meta_title TEXT, meta_description TEXT, slug TEXT UNIQUE NOT NULL, canonical_url TEXT, robots_meta TEXT, og_title TEXT, og_description TEXT, og_image TEXT, schema_json TEXT, hreflang TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE page_seo_config_history(id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER, meta_title TEXT, meta_description TEXT, slug TEXT, canonical_url TEXT, robots_meta TEXT, og_title TEXT, og_description TEXT, og_image TEXT, schema_json TEXT, hreflang TEXT, created_at TEXT)');
        $file = tempnam(sys_get_temp_dir(), 'seo');
        $service = new PageSeoConfigService($pdo, $file);
        $config = new PageSeoConfig(1, 'slug', schemaJson: '');
        $service->save($config);
        $row = $pdo->query('SELECT schema_json FROM page_seo_config WHERE page_id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($row['schema_json']);
        $json = json_decode((string) file_get_contents($file), true);
        $this->assertNull($json['1']['schemaJson']);
        unlink($file);
    }
}
