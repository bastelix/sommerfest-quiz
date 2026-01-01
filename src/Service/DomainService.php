<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DomainNameHelper;
use App\Support\DomainZoneResolver;
use InvalidArgumentException;
use PDO;

/**
 * Provides persistence for admin-managed domain records.
 */
class DomainService
{
    private PDO $pdo;

    private NamespaceValidator $namespaceValidator;

    private DomainZoneResolver $zoneResolver;

    public function __construct(
        PDO $pdo,
        ?NamespaceValidator $namespaceValidator = null,
        ?DomainZoneResolver $zoneResolver = null
    ) {
        $this->pdo = $pdo;
        $this->namespaceValidator = $namespaceValidator ?? new NamespaceValidator();
        $this->zoneResolver = $zoneResolver ?? new DomainZoneResolver();
    }

    /**
     * @return list<array{
     *     id:int,
     *     host:string,
     *     normalized_host:string,
     *     zone:string,
     *     namespace:?string,
     *     label:?string,
     *     is_active:bool
     * }>
     */
    public function listDomains(bool $includeInactive = false): array
    {
        $sql = 'SELECT id, host, normalized_host, zone, namespace, label, is_active FROM domains';
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = TRUE';
        }
        $sql .= ' ORDER BY host ASC';

        $stmt = $this->pdo->query($sql);
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $this->hydrateDomains($rows);
    }

    /**
     * @return array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}
     */
    public function createDomain(
        string $host,
        ?string $label = null,
        ?string $namespace = null,
        bool $isActive = true
    ): array {
        [$displayHost, $normalizedHost, $zone] = $this->prepareHost($host);
        $labelValue = $this->normalizeLabel($label);
        $namespaceValue = $this->normalizeNamespace($namespace);

        $stmt = $this->pdo->prepare(
            'INSERT INTO domains (host, normalized_host, zone, namespace, label, is_active)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT(normalized_host) DO UPDATE SET
                 host = EXCLUDED.host,
                 zone = EXCLUDED.zone,
                 namespace = EXCLUDED.namespace,
                 label = EXCLUDED.label,
                 is_active = EXCLUDED.is_active'
        );
        $stmt->execute([$displayHost, $normalizedHost, $zone, $namespaceValue, $labelValue, $isActive]);

        $domain = $this->getDomainByNormalized($normalizedHost);
        if ($domain === null) {
            throw new InvalidArgumentException('Failed to persist domain.');
        }

        return $domain;
    }

    /**
     * @return array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}|null
     */
    public function updateDomain(
        int $id,
        string $host,
        ?string $label = null,
        ?string $namespace = null,
        bool $isActive = true
    ): ?array {
        [$displayHost, $normalizedHost, $zone] = $this->prepareHost($host);
        $labelValue = $this->normalizeLabel($label);
        $namespaceValue = $this->normalizeNamespace($namespace);

        $conflict = $this->getDomainByNormalized($normalizedHost);
        if ($conflict !== null && $conflict['id'] !== $id) {
            throw new InvalidArgumentException('Domain already exists.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE domains SET host = ?, normalized_host = ?, zone = ?, namespace = ?, label = ?, is_active = ? WHERE id = ?'
        );
        $stmt->execute([$displayHost, $normalizedHost, $zone, $namespaceValue, $labelValue, $isActive, $id]);

        return $this->fetchDomainById($id);
    }

    /**
     * Delete a domain and clean up orphaned certificate zones.
     *
     * @return array{zone:string,zone_removed:bool}|null
     */
    public function deleteDomain(int $id): ?array
    {
        $domain = $this->fetchDomainById($id);
        if ($domain === null) {
            return null;
        }

        $stmt = $this->pdo->prepare('DELETE FROM domains WHERE id = ?');
        $stmt->execute([$id]);

        $zoneRemoved = $this->removeZoneIfUnused($domain['zone']);

        return [
            'zone' => $domain['zone'],
            'zone_removed' => $zoneRemoved,
        ];
    }

    /**
     * Remove the certificate zone if no active domains reference it.
     */
    public function removeZoneIfUnused(string $zone): bool
    {
        $normalized = strtolower(trim($zone));
        if ($normalized === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM domains WHERE zone = ? AND is_active = TRUE');
        $stmt->execute([$normalized]);
        $activeCount = (int) $stmt->fetchColumn();

        if ($activeCount > 0) {
            return false;
        }

        $deleteStmt = $this->pdo->prepare('DELETE FROM certificate_zones WHERE zone = ?');
        $deleteStmt->execute([$normalized]);

        return $deleteStmt->rowCount() > 0;
    }

    public function normalizeDomain(string $domain, bool $stripAdmin = true): string
    {
        return DomainNameHelper::normalize($domain, $stripAdmin);
    }

    /**
     * @return array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}|null
     */
    public function getDomainForHost(string $host, bool $includeInactive = false): ?array
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        $candidates = [];
        $normalized = $this->normalizeDomain($host);
        if ($normalized !== '') {
            $candidates[] = $normalized;
        }
        $marketingHost = $this->normalizeDomain($host, stripAdmin: false);
        if ($marketingHost !== '' && !in_array($marketingHost, $candidates, true)) {
            $candidates[] = $marketingHost;
        }
        $canonicalHost = DomainNameHelper::canonicalizeSlug($host);
        if ($canonicalHost !== '' && !in_array($canonicalHost, $candidates, true)) {
            $candidates[] = $canonicalHost;
        }

        foreach ($candidates as $candidate) {
            $domain = $this->getDomainByNormalized($candidate, $includeInactive);
            if ($domain !== null) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * @return array<string,list<array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}>>
    */
    public function listDomainsByNamespace(bool $includeInactive = false): array
    {
        $domains = $this->listDomains($includeInactive);
        $grouped = [];

        foreach ($domains as $domain) {
            $namespace = $domain['namespace'] ?? '';
            $grouped[$namespace][] = $domain;
        }

        return $grouped;
    }

    /**
     * @return array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}|null
     */
    public function getDomainById(int $id): ?array
    {
        return $this->fetchDomainById($id);
    }

    /**
     * @return array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}|null
     */
    private function getDomainByNormalized(string $normalizedHost, bool $includeInactive = false): ?array
    {
        if ($normalizedHost === '') {
            return null;
        }

        $sql = 'SELECT id, host, normalized_host, zone, namespace, label, is_active FROM domains WHERE normalized_host = ?';
        if (!$includeInactive) {
            $sql .= ' AND is_active = TRUE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$normalizedHost]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrateDomain($row);
    }

    /**
     * @return array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}|null
     */
    private function fetchDomainById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, host, normalized_host, zone, namespace, label, is_active FROM domains WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrateDomain($row);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return list<array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}>
     */
    private function hydrateDomains(array $rows): array
    {
        $domains = [];
        foreach ($rows as $row) {
            $domain = $this->hydrateDomain($row);
            if ($domain !== null) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,host:string,normalized_host:string,zone:string,namespace:?string,label:?string,is_active:bool}|null
     */
    private function hydrateDomain(array $row): ?array
    {
        if (!isset($row['id'], $row['normalized_host'])) {
            return null;
        }

        $id = (int) $row['id'];
        $normalized = strtolower(trim((string) $row['normalized_host']));
        if ($normalized === '') {
            return null;
        }

        $zone = isset($row['zone']) ? strtolower(trim((string) $row['zone'])) : '';
        if ($zone === '') {
            $zone = $normalized;
        }

        $host = isset($row['host']) ? strtolower(trim((string) $row['host'])) : '';
        if ($host === '') {
            $host = $normalized;
        }

        $namespace = null;
        if (array_key_exists('namespace', $row) && $row['namespace'] !== null) {
            $value = strtolower(trim((string) $row['namespace']));
            $namespace = $value !== '' ? $value : null;
        }

        $label = null;
        if (array_key_exists('label', $row) && $row['label'] !== null) {
            $labelValue = trim((string) $row['label']);
            $label = $labelValue !== '' ? $labelValue : null;
        }

        $isActive = (bool) ($row['is_active'] ?? true);

        return [
            'id' => $id,
            'host' => $host,
            'normalized_host' => $normalized,
            'zone' => $zone,
            'namespace' => $namespace,
            'label' => $label,
            'is_active' => $isActive,
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function prepareHost(string $host): array
    {
        $displayHost = $this->normalizeDomain($host, stripAdmin: false);
        if ($displayHost === '') {
            throw new InvalidArgumentException('Invalid domain supplied.');
        }

        $normalizedHost = $this->normalizeDomain($displayHost);
        if ($normalizedHost === '') {
            throw new InvalidArgumentException('Unable to normalize domain.');
        }

        $zone = $this->zoneResolver->deriveZone($displayHost);
        if ($zone === null) {
            throw new InvalidArgumentException('Unable to derive zone.');
        }

        return [$displayHost, $normalizedHost, $zone];
    }

    private function normalizeLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $trimmed = trim($label);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeNamespace(?string $namespace): ?string
    {
        if ($namespace === null) {
            return null;
        }

        $normalized = $this->namespaceValidator->normalizeCandidate($namespace);
        if ($normalized === null) {
            $trimmed = trim($namespace);
            if ($trimmed !== '') {
                throw new InvalidArgumentException('Invalid namespace supplied.');
            }

            return null;
        }

        return $normalized;
    }
}
