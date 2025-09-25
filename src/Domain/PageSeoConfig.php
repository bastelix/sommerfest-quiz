<?php

declare(strict_types=1);

namespace App\Domain;

use JsonSerializable;

/**
 * SEO configuration associated with a Page.
 */
class PageSeoConfig implements JsonSerializable
{
    private int $pageId;

    private ?string $metaTitle;

    private ?string $metaDescription;

    private string $slug;

    private ?string $domain;

    private ?string $canonicalUrl;

    private ?string $robotsMeta;

    private ?string $ogTitle;

    private ?string $ogDescription;

    private ?string $ogImage;

    private ?string $schemaJson;

    private ?string $hreflang;

    /** @var Page|null */
    private $page;

    public function __construct(
        int $pageId,
        string $slug,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $canonicalUrl = null,
        ?string $robotsMeta = null,
        ?string $ogTitle = null,
        ?string $ogDescription = null,
        ?string $ogImage = null,
        ?string $schemaJson = null,
        ?string $hreflang = null,
        ?string $domain = null
    ) {
        $this->pageId = $pageId;
        $this->slug = $slug;
        $this->domain = $domain;
        $this->metaTitle = $metaTitle;
        $this->metaDescription = $metaDescription;
        $this->canonicalUrl = $canonicalUrl;
        $this->robotsMeta = $robotsMeta;
        $this->ogTitle = $ogTitle;
        $this->ogDescription = $ogDescription;
        $this->ogImage = $ogImage;
        $this->schemaJson = $schemaJson;
        $this->hreflang = $hreflang;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getRobotsMeta(): ?string
    {
        return $this->robotsMeta;
    }

    public function getOgTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function getOgImage(): ?string
    {
        return $this->ogImage;
    }

    public function getSchemaJson(): ?string
    {
        return $this->schemaJson;
    }

    public function getHreflang(): ?string
    {
        return $this->hreflang;
    }

    /**
     * @return Page|null
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param Page $page
     */
    public function setPage($page): void
    {
        $this->page = $page;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'pageId' => $this->pageId,
            'metaTitle' => $this->metaTitle,
            'metaDescription' => $this->metaDescription,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'canonicalUrl' => $this->canonicalUrl,
            'robotsMeta' => $this->robotsMeta,
            'ogTitle' => $this->ogTitle,
            'ogDescription' => $this->ogDescription,
            'ogImage' => $this->ogImage,
            'schemaJson' => $this->schemaJson,
            'hreflang' => $this->hreflang,
        ];
    }
}
