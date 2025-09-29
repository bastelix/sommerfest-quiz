<?php

namespace App\Twig;

use App\Service\TranslationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TranslationExtension extends AbstractExtension
{
    private TranslationService $translator;

    public function __construct(TranslationService $translator) {
        $this->translator = $translator;
    }

    public function getFunctions(): array {
        return [
            new TwigFunction('t', [$this->translator, 'translate']),
            new TwigFunction('locale', [$this->translator, 'getLocale']),
        ];
    }
}
