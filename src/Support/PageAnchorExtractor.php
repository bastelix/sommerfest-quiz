<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMXPath;

class PageAnchorExtractor
{
    private const BLOCK_TYPE_LABELS = [
        'hero' => 'Hero',
        'feature_list' => 'Funktionen',
        'content_slider' => 'Slider',
        'process_steps' => 'Ablauf',
        'testimonial' => 'Stimmen',
        'rich_text' => 'Text',
        'info_media' => 'Info & Medien',
        'stat_strip' => 'Kennzahlen',
        'audience_spotlight' => 'Anwendungsfälle',
        'package_summary' => 'Pakete',
        'contact_form' => 'Kontaktformular',
        'faq' => 'Häufige Fragen',
        'cta' => 'Call to Action',
        'proof' => 'Nachweise',
        'latest_news' => 'Neuigkeiten',
    ];

    /**
     * Extract anchor IDs from page content, covering CMS JSON meta anchors and HTML id attributes.
     *
     * @return array<int, string>
     */
    public function extractAnchorIds(string $content): array
    {
        return array_column($this->extractAnchorsWithMeta($content), 'anchor');
    }

    /**
     * Extract anchors with metadata (block type and title) from page content.
     *
     * @return array<int, array{anchor: string, blockType: string, blockTitle: string}>
     */
    public function extractAnchorsWithMeta(string $content): array
    {
        $anchors = [];

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            if (isset($decoded['blocks']) && is_array($decoded['blocks'])) {
                $anchors = array_merge($anchors, $this->extractAnchorsFromBlocksWithMeta($decoded['blocks']));
            } else {
                foreach ($this->extractAnchorsFromArray($decoded) as $anchor) {
                    $anchors[] = ['anchor' => $anchor, 'blockType' => '', 'blockTitle' => ''];
                }
            }
        }

        if (str_contains($content, '<')) {
            foreach ($this->extractAnchorsFromHtml($content) as $anchor) {
                $anchors[] = ['anchor' => $anchor, 'blockType' => '', 'blockTitle' => ''];
            }
        }

        $unique = [];
        $seen = [];
        foreach ($anchors as $entry) {
            $anchor = $entry['anchor'] ?? '';
            if ($anchor === '' || isset($seen[$anchor])) {
                continue;
            }
            $seen[$anchor] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int, string>
     */
    private function extractAnchorsFromArray(array $data): array
    {
        $anchors = [];

        // Check for top-level blocks array and auto-generate anchors
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            $anchors = array_merge($anchors, $this->extractAnchorsFromBlocks($data['blocks']));
            return $anchors;
        }

        foreach ($data as $key => $value) {
            if ($key === 'anchor' && is_string($value)) {
                $normalized = $this->normalizeAnchor($value);
                if ($normalized !== null) {
                    $anchors[] = $normalized;
                }
            }

            if (is_array($value)) {
                $anchors = array_merge($anchors, $this->extractAnchorsFromArray($value));
            }
        }

        return $anchors;
    }

    /**
     * Extract anchors from a blocks array, auto-generating from block type when meta.anchor is not set.
     *
     * @param array<int, mixed> $blocks
     * @return array<int, string>
     */
    private function extractAnchorsFromBlocks(array $blocks): array
    {
        return array_column($this->extractAnchorsFromBlocksWithMeta($blocks), 'anchor');
    }

    /**
     * Extract anchors with metadata from a blocks array.
     *
     * @param array<int, mixed> $blocks
     * @return array<int, array{anchor: string, blockType: string, blockTitle: string}>
     */
    private function extractAnchorsFromBlocksWithMeta(array $blocks): array
    {
        $anchors = [];
        $usedAnchors = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? '';
            $blockType = is_string($type) ? $type : '';
            $blockTitle = $this->resolveBlockTitle($block);

            $explicitAnchor = $block['meta']['anchor'] ?? null;
            if (is_string($explicitAnchor)) {
                $normalized = $this->normalizeAnchor($explicitAnchor);
                if ($normalized !== null) {
                    $anchors[] = [
                        'anchor' => $normalized,
                        'blockType' => $blockType,
                        'blockTitle' => $blockTitle,
                    ];
                    $usedAnchors[$normalized] = true;
                    continue;
                }
            }

            if ($blockType === '') {
                continue;
            }

            $base = strtolower(str_replace('_', '-', $blockType));
            $anchor = $base;
            $counter = 2;
            while (isset($usedAnchors[$anchor])) {
                $anchor = $base . '-' . $counter;
                $counter++;
            }
            $usedAnchors[$anchor] = true;
            $anchors[] = [
                'anchor' => $anchor,
                'blockType' => $blockType,
                'blockTitle' => $blockTitle,
            ];
        }

        return $anchors;
    }

    private function resolveBlockTitle(array $block): string
    {
        $data = $block['data'] ?? [];
        if (!is_array($data)) {
            return '';
        }

        foreach (['title', 'headline', 'heading'] as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * Resolve a human-readable label for a block type.
     */
    public static function blockTypeLabel(string $type): string
    {
        return self::BLOCK_TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * @return array<int, string>
     */
    private function extractAnchorsFromHtml(string $content): array
    {
        $document = new DOMDocument();
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML('<?xml encoding="utf-8"?>' . $content);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }

        if ($loaded === false) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[@id]');
        if ($nodes === false) {
            return [];
        }

        $anchors = [];
        foreach ($nodes as $node) {
            if ($node->attributes === null) {
                continue;
            }
            $idAttribute = $node->attributes->getNamedItem('id');
            if ($idAttribute === null) {
                continue;
            }
            $normalized = $this->normalizeAnchor((string) $idAttribute->nodeValue);
            if ($normalized !== null) {
                $anchors[] = $normalized;
            }
        }

        return $anchors;
    }

    private function normalizeAnchor(string $anchor): ?string
    {
        $trimmed = trim($anchor);
        if ($trimmed === '') {
            return null;
        }

        $normalized = ltrim($trimmed, '#');
        return $normalized === '' ? null : $normalized;
    }
}
