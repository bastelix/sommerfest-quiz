<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CssSanitizer;
use PHPUnit\Framework\TestCase;

class CssSanitizerTest extends TestCase
{
    private CssSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new CssSanitizer();
    }

    public function testPreservesValidCss(): void
    {
        $css = ".uk-card { border-radius: 12px; }\n.uk-button-primary { background: #ff0000; }";
        $this->assertSame($css, $this->sanitizer->sanitize($css));
    }

    public function testPreservesCustomProperties(): void
    {
        $css = ':root { --brand-primary: #1e87f0; }';
        $this->assertSame($css, $this->sanitizer->sanitize($css));
    }

    public function testStripsScriptTags(): void
    {
        $css = '.foo { color: red; }<script>alert(1)</script>';
        $result = $this->sanitizer->sanitize($css);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
        $this->assertStringContainsString('.foo { color: red; }', $result);
    }

    public function testStripsStyleTags(): void
    {
        $css = '</style><script>alert(1)</script><style>';
        $result = $this->sanitizer->sanitize($css);
        $this->assertStringNotContainsString('</style>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testRemovesImportRules(): void
    {
        $css = '@import url("https://evil.com/xss.css"); .foo { color: red; }';
        $result = $this->sanitizer->sanitize($css);
        // The @import url(...) directive is replaced; only the comment marker remains
        $this->assertStringNotContainsString('@import url', $result);
        $this->assertStringContainsString('.foo { color: red; }', $result);
    }

    public function testRemovesExpression(): void
    {
        $css = '.foo { width: expression(alert(1)); }';
        $result = $this->sanitizer->sanitize($css);
        // expression( is neutralized -- the function call syntax is broken
        $this->assertDoesNotMatchRegularExpression('/expression\s*\(/', $result);
    }

    public function testRemovesJavascriptUrl(): void
    {
        $css = '.foo { background: url(javascript:alert(1)); }';
        $result = $this->sanitizer->sanitize($css);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testRemovesVbscriptUrl(): void
    {
        $css = '.foo { background: url(vbscript:alert(1)); }';
        $result = $this->sanitizer->sanitize($css);
        $this->assertStringNotContainsString('vbscript:', $result);
    }

    public function testRemovesDataTextHtmlUrl(): void
    {
        $css = '.foo { background: url(data:text/html,<script>alert(1)</script>); }';
        $result = $this->sanitizer->sanitize($css);
        // data:text/html in a url() context is neutralized
        $this->assertDoesNotMatchRegularExpression('/url\s*\(\s*["\']?\s*data\s*:\s*text\/html/i', $result);
    }

    public function testPreservesSafeDataUrls(): void
    {
        $css = '.foo { background: url(data:image/png;base64,abc123); }';
        $result = $this->sanitizer->sanitize($css);
        $this->assertStringContainsString('data:image/png', $result);
    }

    public function testRemovesMozBinding(): void
    {
        $css = '.foo { -moz-binding: url("https://evil.com/xbl"); }';
        $result = $this->sanitizer->sanitize($css);
        // The -moz-binding property is neutralized (replaced with _removed:)
        $this->assertDoesNotMatchRegularExpression('/-moz-binding\s*:/', $result);
    }

    public function testRemovesBehavior(): void
    {
        $css = '.foo { behavior: url("https://evil.com/xss.htc"); }';
        $result = $this->sanitizer->sanitize($css);
        // The behavior property is neutralized (replaced with _removed:)
        $this->assertDoesNotMatchRegularExpression('/\bbehavior\s*:/', $result);
    }

    public function testHandlesEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }

    public function testHandlesMultipleVectors(): void
    {
        $css = "@import url('evil.css'); .foo { behavior: url(x.htc); -moz-binding: url(y); width: expression(1); }";
        $result = $this->sanitizer->sanitize($css);
        $this->assertStringNotContainsString('@import url', $result);
        $this->assertDoesNotMatchRegularExpression('/\bbehavior\s*:/', $result);
        $this->assertDoesNotMatchRegularExpression('/-moz-binding\s*:/', $result);
        $this->assertDoesNotMatchRegularExpression('/expression\s*\(/', $result);
    }
}
