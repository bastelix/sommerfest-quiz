<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DuplicateNamespaceException;
use App\Exception\NamespaceInUseException;
use App\Exception\NamespaceNotFoundException;
use App\Repository\NamespaceRepository;
use InvalidArgumentException;

final class NamespaceService
{
    private NamespaceValidator $validator;

    public function __construct(private NamespaceRepository $repository, ?NamespaceValidator $validator = null)
    {
        $this->validator = $validator ?? new NamespaceValidator();
    }

    /**
     * @return list<array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}>
     */
    public function all(): array
    {
        $entries = $this->repository->list();
        $existing = [];
        foreach ($entries as $entry) {
            $existing[$entry['namespace']] = true;
        }

        $knownNamespaces = $this->repository->listKnownNamespaces();
        $knownNamespaces[] = PageService::DEFAULT_NAMESPACE;
        foreach ($knownNamespaces as $candidate) {
            $normalized = $this->validator->normalizeCandidate($candidate);
            if ($normalized === null || isset($existing[$normalized])) {
                continue;
            }
            $entries[] = [
                'namespace' => $normalized,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
            $existing[$normalized] = true;
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => strcmp($left['namespace'], $right['namespace'])
        );

        return $entries;
    }

    /**
     * @return array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}
     */
    public function create(string $namespace, ?string $label = null): array
    {
        $normalized = $this->validator->normalize($namespace);
        $this->validator->assertValid($normalized);
        $normalizedLabel = $this->normalizeLabel($label);

        if ($this->repository->exists($normalized)) {
            throw new DuplicateNamespaceException('namespace-exists');
        }

        $this->repository->create($normalized, $normalizedLabel);

        return $this->repository->find($normalized) ?? [
            'namespace' => $normalized,
            'label' => $normalizedLabel,
            'is_active' => true,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @return array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}
     */
    public function rename(
        string $namespace,
        string $newNamespace,
        ?string $label = null,
        bool $updateLabel = false
    ): array {
        $source = $this->validator->normalize($namespace);
        $target = $this->validator->normalize($newNamespace);
        $normalizedLabel = $updateLabel ? $this->normalizeLabel($label) : null;

        if ($source === PageService::DEFAULT_NAMESPACE) {
            throw new InvalidArgumentException('default-namespace');
        }

        $this->validator->assertValid($target);

        if (!$this->repository->exists($source)) {
            throw new NamespaceNotFoundException('namespace-missing');
        }

        if ($source !== $target && $this->repository->exists($target)) {
            throw new DuplicateNamespaceException('namespace-exists');
        }

        $this->repository->update($source, $target, $normalizedLabel, null, $updateLabel);

        return $this->repository->find($target) ?? [
            'namespace' => $target,
            'label' => $normalizedLabel,
            'is_active' => true,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * Remove a namespace.
     */
    public function delete(string $namespace): void
    {
        $normalized = $this->validator->normalize($namespace);

        if ($normalized === PageService::DEFAULT_NAMESPACE) {
            throw new InvalidArgumentException('default-namespace');
        }

        if (!$this->repository->exists($normalized)) {
            throw new NamespaceNotFoundException('namespace-missing');
        }

        $usage = $this->repository->findUsage($normalized);
        if ($usage !== []) {
            throw new NamespaceInUseException($usage);
        }

        $this->repository->deactivate($normalized);
    }

    private function normalizeLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $normalized = trim($label);

        return $normalized === '' ? null : $normalized;
    }
}
