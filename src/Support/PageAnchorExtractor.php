<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMXPath;

class PageAnchorExtractor
{
    /**
     * Extract anchor IDs from page content, covering CMS JSON meta anchors and HTML id attributes.
     *
     * @return array<int, string>
     */
    public function extractAnchorIds(string $content): array
    {
        $anchors = [];

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $anchors = array_merge($anchors, $this->extractAnchorsFromArray($decoded));
        }

        if (str_contains($content, '<')) {
            $anchors = array_merge($anchors, $this->extractAnchorsFromHtml($content));
        }

        $unique = [];
        $seen = [];
        foreach ($anchors as $anchor) {
            if ($anchor === '' || isset($seen[$anchor])) {
                continue;
            }
            $seen[$anchor] = true;
            $unique[] = $anchor;
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
        $anchors = [];
        $usedAnchors = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $explicitAnchor = $block['meta']['anchor'] ?? null;
            if (is_string($explicitAnchor)) {
                $normalized = $this->normalizeAnchor($explicitAnchor);
                if ($normalized !== null) {
                    $anchors[] = $normalized;
                    $usedAnchors[$normalized] = true;
                    continue;
                }
            }

            $type = $block['type'] ?? null;
            if (!is_string($type) || $type === '') {
                continue;
            }

            $base = strtolower(str_replace('_', '-', $type));
            $anchor = $base;
            $counter = 2;
            while (isset($usedAnchors[$anchor])) {
                $anchor = $base . '-' . $counter;
                $counter++;
            }
            $usedAnchors[$anchor] = true;
            $anchors[] = $anchor;
        }

        return $anchors;
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
