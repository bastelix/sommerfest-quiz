<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use DOMDocument;
use DOMElement;
use DOMNode;

use function array_filter;
use function array_map;
use function explode;
use function implode;
use function in_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;

final class PageAiHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'section',
        'div',
        'header',
        'footer',
        'main',
        'article',
        'aside',
        'nav',
        'p',
        'span',
        'strong',
        'em',
        'b',
        'i',
        'small',
        'sup',
        'sub',
        'br',
        'hr',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'a',
        'ul',
        'ol',
        'li',
        'figure',
        'figcaption',
        'img',
        'blockquote',
        'cite',
        'table',
        'thead',
        'tbody',
        'tr',
        'th',
        'td',
        'form',
        'label',
        'input',
        'textarea',
        'button',
        'select',
        'option',
        'video',
        'source',
    ];

    private const DROP_TAGS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'id',
        'class',
        'href',
        'target',
        'rel',
        'src',
        'alt',
        'title',
        'type',
        'name',
        'value',
        'placeholder',
        'rows',
        'cols',
        'for',
        'role',
        'method',
        'action',
        'width',
        'height',
        'loading',
        'aria-label',
        'aria-labelledby',
        'aria-describedby',
        'aria-hidden',
        'required',
        'checked',
        'disabled',
        'selected',
        'style',
    ];

    private const ALLOWED_STYLE_PROPERTIES = [
        '--qr-landing-primary',
        '--qr-landing-bg',
        '--qr-landing-accent',
    ];

    public function sanitize(string $html): string
    {
        $normalized = trim($html);
        if ($normalized === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $normalized,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->sanitizeNode($doc);

        $output = trim($doc->saveHTML() ?: '');
        $output = preg_replace('/^<\?xml.*?\?>/i', '', $output) ?? $output;

        return trim($output);
    }

    private function sanitizeNode(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                if (in_array($tag, self::DROP_TAGS, true)) {
                    $this->removeNode($node);
                    return;
                }
                $children = [];
                foreach ($node->childNodes as $child) {
                    $children[] = $child;
                }
                foreach ($children as $child) {
                    $this->sanitizeNode($child);
                }
                $this->unwrapNode($node);
                return;
            }

            $this->sanitizeAttributes($node);
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $this->sanitizeNode($child);
        }
    }

    private function sanitizeAttributes(DOMElement $node): void
    {
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $attributes[] = $attribute;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;

            if ($name === 'class') {
                $filtered = $this->filterUiKitClasses($value);
                if ($filtered === '') {
                    $node->removeAttribute($name);
                } else {
                    $node->setAttribute($name, $filtered);
                }
                continue;
            }

            if ($name === 'style') {
                $filtered = $this->filterStyle($value);
                if ($filtered === '') {
                    $node->removeAttribute($name);
                } else {
                    $node->setAttribute($name, $filtered);
                }
                continue;
            }

            if (str_starts_with($name, 'data-') || str_starts_with($name, 'uk-')) {
                continue;
            }

            if (str_starts_with($name, 'aria-')) {
                continue;
            }

            if (!in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                $node->removeAttribute($name);
                continue;
            }

            if ($name === 'id' && !$this->isValidId($value)) {
                $node->removeAttribute($name);
                continue;
            }

            if (($name === 'href' || $name === 'src') && !$this->isSafeUrl($value)) {
                $node->removeAttribute($name);
                continue;
            }

            if ($name === 'target' && !$this->isAllowedTarget($value)) {
                $node->removeAttribute($name);
                continue;
            }
        }
    }

    private function filterUiKitClasses(string $value): string
    {
        $classes = array_filter(
            array_map('trim', preg_split('/\s+/', $value) ?: []),
            fn (string $class): bool => $class !== '' && str_starts_with($class, 'uk-')
                && preg_match('/^uk-[A-Za-z0-9@:_-]+$/', $class) === 1
        );

        return implode(' ', $classes);
    }

    private function filterStyle(string $value): string
    {
        $parts = array_filter(array_map('trim', explode(';', $value)));
        $allowed = [];
        foreach ($parts as $part) {
            if (!str_contains($part, ':')) {
                continue;
            }
            [$property, $rawValue] = array_map('trim', explode(':', $part, 2));
            if (!in_array($property, self::ALLOWED_STYLE_PROPERTIES, true)) {
                continue;
            }
            if (!$this->isSafeStyleValue($rawValue)) {
                continue;
            }
            $allowed[] = $property . ': ' . $rawValue;
        }

        return implode('; ', $allowed);
    }

    private function isSafeStyleValue(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }
        if (str_contains($normalized, 'expression')
            || str_contains($normalized, 'url(')
            || str_contains($normalized, 'javascript:')
            || str_contains($normalized, 'data:')
        ) {
            return false;
        }

        return preg_match('/^[#a-z0-9(),.%\s-]+$/i', $normalized) === 1;
    }

    private function isSafeUrl(string $value): bool
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return false;
        }
        $lower = strtolower($normalized);
        if (str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'vbscript:')
        ) {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $normalized) === 1) {
            $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));
            return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
        }

        return true;
    }

    private function isAllowedTarget(string $value): bool
    {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['_blank', '_self', '_parent', '_top'], true);
    }

    private function isValidId(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9\-_:.]*$/', trim($value)) === 1;
    }

    private function unwrapNode(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }
        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    private function removeNode(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if ($parent !== null) {
            $parent->removeChild($node);
        }
    }
}
