<?php

declare(strict_types=1);

namespace App\Service;

class TranslationService
{
    private array $translations = [];
    private string $locale;

    public function __construct(string $locale = 'de') {
        $this->loadLocale($locale);
    }

    public function loadLocale(string $locale): void {
        $file = __DIR__ . '/../../resources/lang/' . $locale . '.php';
        if (!is_readable($file)) {
            $locale = 'de';
            $file = __DIR__ . '/../../resources/lang/de.php';
        }
        $this->locale = $locale;
        $this->translations = is_readable($file) ? require $file : [];
    }

    public function translate(string $key): string {
        return $this->translations[$key] ?? $key;
    }

    public function getLocale(): string {
        return $this->locale;
    }
}
