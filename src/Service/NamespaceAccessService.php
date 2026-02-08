<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Roles;

/**
 * Resolve namespace visibility for the current session user.
 */
class NamespaceAccessService
{
    /**
     * @return list<string>
     */
    public function resolveAllowedNamespaces(?string $role): array
    {
        if ($this->isAdmin($role)) {
            return [];
        }

        $entries = $_SESSION['user']['namespaces'] ?? null;
        if (!is_array($entries)) {
            return [];
        }

        $allowed = [];
        foreach ($entries as $entry) {
            $namespace = '';
            if (is_array($entry)) {
                $namespace = (string) ($entry['namespace'] ?? '');
            } elseif (is_string($entry)) {
                $namespace = $entry;
            }

            $namespace = strtolower(trim($namespace));
            if ($namespace === '' || in_array($namespace, $allowed, true)) {
                continue;
            }

            $allowed[] = $namespace;
        }

        return $allowed;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<string> $allowed
     *
     * @return list<array<string, mixed>>
     */
    public function filterNamespaceEntries(array $entries, array $allowed, ?string $role): array
    {
        if ($this->isAdmin($role)) {
            return $entries;
        }

        if ($allowed === []) {
            return [];
        }

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => in_array(
                strtolower(trim((string) ($entry['namespace'] ?? ''))),
                $allowed,
                true
            )
        ));
    }

    /**
     * @param list<string> $namespaces
     * @param list<string> $allowed
     *
     * @return list<string>
     */
    public function filterNamespaceList(array $namespaces, array $allowed, ?string $role): array
    {
        if ($this->isAdmin($role)) {
            return $namespaces;
        }

        if ($allowed === []) {
            return [];
        }

        $filtered = [];
        foreach ($namespaces as $namespace) {
            $normalized = strtolower(trim((string) $namespace));
            if ($normalized === '' || !in_array($normalized, $allowed, true)) {
                continue;
            }

            $filtered[] = $normalized;
        }

        return array_values(array_unique($filtered));
    }

    public function shouldExposeNamespace(string $namespace, array $allowed, ?string $role): bool
    {
        if ($this->isAdmin($role)) {
            return true;
        }

        if ($allowed === []) {
            return false;
        }

        return in_array(strtolower(trim($namespace)), $allowed, true);
    }

    /**
     * Filter a list of events by the user's allowed namespaces.
     *
     * @param list<array<string, mixed>> $events
     * @param list<string> $allowed
     *
     * @return list<array<string, mixed>>
     */
    public function filterEventsByNamespace(array $events, array $allowed, ?string $role): array
    {
        if ($this->isAdmin($role)) {
            return $events;
        }

        if ($allowed === []) {
            return [];
        }

        return array_values(array_filter(
            $events,
            static fn (array $event): bool => in_array(
                strtolower(trim((string) ($event['namespace'] ?? 'default'))),
                $allowed,
                true
            )
        ));
    }

    private function isAdmin(?string $role): bool
    {
        return $role === Roles::ADMIN;
    }
}
