<?php

declare(strict_types=1);

namespace App\Service\Seo;

/**
 * Enhances JSON-LD structured data with AI-optimized schemas.
 *
 * Generates FAQPage, SoftwareApplication, and Organization schemas
 * with speakable properties for better AI and voice assistant discovery.
 */
final class SchemaEnhancer
{
    /**
     * Generate a FAQPage JSON-LD schema from question/answer pairs.
     *
     * @param array<int,array{question:string,answer:string}> $pairs
     */
    public function faqPageSchema(array $pairs): string
    {
        $mainEntity = [];
        foreach ($pairs as $pair) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $pair['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $pair['answer'],
                ],
            ];
        }

        return $this->encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ]);
    }

    /**
     * Generate a SoftwareApplication schema for product pages.
     */
    public function softwareApplicationSchema(
        string $name,
        string $description,
        string $url,
        ?string $screenshotUrl = null
    ): string {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'applicationCategory' => 'EventApplication',
            'operatingSystem' => 'Web',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
                'description' => 'Kostenloser Einstieg verfügbar',
            ],
        ];

        if ($screenshotUrl !== null) {
            $schema['screenshot'] = $screenshotUrl;
        }

        return $this->encode($schema);
    }

    /**
     * Generate an Organization schema with full contact details.
     */
    public function organizationSchema(
        string $name,
        string $url,
        ?string $logoUrl = null,
        ?string $email = null,
        array $sameAs = []
    ): string {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => $url,
        ];

        if ($logoUrl !== null) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logoUrl,
            ];
        }

        if ($email !== null) {
            $schema['contactPoint'] = [
                '@type' => 'ContactPoint',
                'email' => $email,
                'contactType' => 'customer service',
            ];
        }

        if ($sameAs !== []) {
            $schema['sameAs'] = $sameAs;
        }

        return $this->encode($schema);
    }

    /**
     * Add speakable property to an existing schema for voice assistant support.
     *
     * @param array<string,mixed> $schema
     * @param string[] $cssSelectors CSS selectors for speakable content
     * @return array<string,mixed>
     */
    public function withSpeakable(array $schema, array $cssSelectors = ['h1', '.content', '.faq-answer']): array
    {
        $schema['speakable'] = [
            '@type' => 'SpeakableSpecification',
            'cssSelector' => $cssSelectors,
        ];

        return $schema;
    }

    /**
     * Generate a WebPage schema with speakable for a generic content page.
     */
    public function webPageSchema(
        string $name,
        string $description,
        string $url,
        ?string $datePublished = null,
        ?string $dateModified = null
    ): string {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'speakable' => [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => ['h1', '.content', 'article'],
            ],
        ];

        if ($datePublished !== null) {
            $schema['datePublished'] = $datePublished;
        }
        if ($dateModified !== null) {
            $schema['dateModified'] = $dateModified;
        }

        return $this->encode($schema);
    }

    /**
     * @param array<string,mixed> $schema
     */
    private function encode(array $schema): string
    {
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $json !== false ? $json : '{}';
    }
}
