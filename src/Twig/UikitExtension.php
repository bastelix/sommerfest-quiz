<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class UikitExtension extends AbstractExtension
{
    public function getFilters(): array {
        return [
            new TwigFilter('uikitify', [$this, 'uikitify'], ['is_safe' => ['html']]),
        ];
    }

    public function uikitify(string $html): string {
        $patterns = [
            '/<h([1-6])([^>]*)>/i',
            '/<\/h([1-6])>/i',
            '/<(strong|b)>/i',
            '/<\/(strong|b)>/i',
            '/<(em|i)>/i',
            '/<\/(em|i)>/i',
        ];
        $replacements = [
            '<h$1 class="uk-heading-bullet"$2>',
            '</h$1>',
            '<span class="uk-text-bold">',
            '</span>',
            '<span class="uk-text-italic">',
            '</span>',
        ];
        return preg_replace($patterns, $replacements, $html);
    }
}
