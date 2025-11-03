<?php

declare(strict_types=1);

namespace App\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use IntlDateFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateTimeFormatExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_datetime', [$this, 'formatDateTime'], ['is_variadic' => true]),
        ];
    }

    /**
     * @param DateTimeInterface|string|int|null $value
     * @param array<string, mixed>              $options
     */
    public function formatDateTime(DateTimeInterface|string|int|null $value, array $options = []): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!$value instanceof DateTimeInterface) {
            try {
                if (is_int($value)) {
                    $value = (new DateTimeImmutable('@' . $value))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                } else {
                    $value = new DateTimeImmutable((string) $value);
                }
            } catch (Exception) {
                return '';
            }
        }

        if (!class_exists(IntlDateFormatter::class)) {
            return $value->format('d.m.Y H:i');
        }

        $pattern = $options['pattern'] ?? "dd.MM.yyyy 'um' HH:mm 'Uhr'";
        $locale = $options['locale'] ?? 'de';
        $timezone = $options['timezone'] ?? $value->getTimezone()->getName();
        $calendar = $options['calendar'] ?? IntlDateFormatter::GREGORIAN;

        /** @var IntlDateFormatter|false $formatter */
        $formatter = IntlDateFormatter::create($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $timezone, $calendar, $pattern);
        if ($formatter === false) {
            return $value->format('Y-m-d H:i');
        }

        $formatted = $formatter->format($value);
        if ($formatted === false) {
            return $value->format('Y-m-d H:i');
        }

        return $formatted;
    }
}
