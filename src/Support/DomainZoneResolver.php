<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Derives the registrable domain (zone) for hosts using a bundled
 * public suffix list. The resolver intentionally avoids environment-based
 * configuration so that the application remains the single source of truth
 * for domain metadata.
 */
final class DomainZoneResolver
{
    private string $pslPath;

    /** @var array<string,bool> */
    private array $exactRules = [];

    /** @var array<string,bool> */
    private array $wildcardRules = [];

    /** @var array<string,bool> */
    private array $exceptionRules = [];

    public function __construct(?string $pslPath = null)
    {
        $this->pslPath = $pslPath ?? dirname(__DIR__, 2) . '/resources/public_suffix_list.dat';
        $this->loadRules();
    }

    public function deriveZone(string $host): ?string
    {
        $normalized = DomainNameHelper::normalize($host, stripAdmin: false);
        if ($normalized === '') {
            return null;
        }

        $ascii = idn_to_ascii($normalized, IDNA_DEFAULT | IDNA_NONTRANSITIONAL_TO_ASCII);
        $candidate = is_string($ascii) && $ascii !== '' ? strtolower($ascii) : strtolower($normalized);
        $labels = array_values(array_filter(explode('.', $candidate), static fn (string $value): bool => $value !== ''));

        if ($labels === [] || count($labels) === 1) {
            return $candidate;
        }

        $publicSuffix = $this->determinePublicSuffix($labels);
        $suffixLabels = $publicSuffix !== '' ? explode('.', $publicSuffix) : [];

        if ($suffixLabels === [] || count($labels) <= count($suffixLabels)) {
            return $candidate;
        }

        $registrable = array_slice($labels, -1 - count($suffixLabels));

        return implode('.', $registrable);
    }

    /**
     * @param list<string> $labels
     */
    private function determinePublicSuffix(array $labels): string
    {
        $ruleLabels = null;

        // Exception rules take precedence and drop one label.
        foreach ($this->exceptionRules as $exception => $_) {
            $exceptionLabels = explode('.', $exception);
            $startIndex = count($labels) - count($exceptionLabels);
            if ($startIndex < 0) {
                continue;
            }

            $candidate = implode('.', array_slice($labels, $startIndex));
            if ($candidate === $exception) {
                $ruleLabels = array_slice($labels, $startIndex + 1);
                break;
            }
        }

        if ($ruleLabels === null) {
            $ruleLabels = $this->matchWildcardOrExact($labels);
        }

        if ($ruleLabels === null || $ruleLabels === []) {
            return (string) end($labels);
        }

        return implode('.', $ruleLabels);
    }

    /**
     * @param list<string> $labels
     * @return list<string>|null
     */
    private function matchWildcardOrExact(array $labels): ?array
    {
        $matchedRule = null;

        $labelCount = count($labels);
        for ($index = 0; $index < $labelCount; $index++) {
            $candidateLabels = array_slice($labels, $index);
            $candidate = implode('.', $candidateLabels);

            if (isset($this->exactRules[$candidate])) {
                $matchedRule = $candidateLabels;
                break;
            }

            $wildcard = '*.' . implode('.', array_slice($labels, $index + 1));
            if (isset($this->wildcardRules[$wildcard])) {
                $matchedRule = $candidateLabels;
                break;
            }
        }

        if ($matchedRule !== null) {
            return $matchedRule;
        }

        return array_slice($labels, -1);
    }

    private function loadRules(): void
    {
        if (!is_file($this->pslPath)) {
            throw new RuntimeException('Public suffix list not found at ' . $this->pslPath);
        }

        $lines = file($this->pslPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read public suffix list at ' . $this->pslPath);
        }

        foreach ($lines as $line) {
            $trimmed = strtolower(trim($line));
            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }

            if (str_starts_with($trimmed, '!')) {
                $this->exceptionRules[substr($trimmed, 1)] = true;
                continue;
            }

            if (str_starts_with($trimmed, '*.')) {
                $this->wildcardRules[$trimmed] = true;
                continue;
            }

            $this->exactRules[$trimmed] = true;
        }
    }
}
