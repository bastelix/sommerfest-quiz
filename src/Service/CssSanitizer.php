<?php

declare(strict_types=1);

namespace App\Service;

class CssSanitizer
{
    /**
     * Sanitize user-provided CSS to prevent XSS vectors.
     *
     * Strips dangerous constructs while preserving valid CSS rules.
     */
    public function sanitize(string $css): string
    {
        // Strip HTML tags entirely (prevents <script>, </style> injection)
        $css = strip_tags($css);

        // Remove @import rules (prevents loading external stylesheets)
        $css = (string) preg_replace('/@import\s+[^;]+;?/i', '/* @import removed */', $css);

        // Remove expression() (IE XSS vector)
        $css = (string) preg_replace('/expression\s*\(/i', '/* expression removed */(', $css);

        // Remove url() with dangerous schemes (javascript:, vbscript:)
        $css = (string) preg_replace(
            '/url\s*\(\s*["\']?\s*(javascript|vbscript)\s*:/i',
            'url(about:',
            $css
        );

        // Remove url() with data:text/html (XSS via data URI)
        $css = (string) preg_replace(
            '/url\s*\(\s*["\']?\s*data\s*:\s*text\/html/i',
            'url(about:blocked',
            $css
        );

        // Remove -moz-binding (Firefox XSS vector)
        $css = (string) preg_replace('/-moz-binding\s*:/i', '/* -moz-binding removed */ _removed:', $css);

        // Remove behavior: property (IE HTC XSS vector)
        $css = (string) preg_replace('/\bbehavior\s*:/i', '/* behavior removed */ _removed:', $css);

        return $css;
    }
}
