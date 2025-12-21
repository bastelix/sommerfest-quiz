<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DuplicateNamespaceException;
use App\Exception\NamespaceNotFoundException;
use App\Repository\NamespaceRepository;
use InvalidArgumentException;

final class NamespaceService
{
    public function __construct(private NamespaceRepository $repository)
    {
    }

    /**
     * @return list<array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}>
     */
    public function all(): array
    {
        return $this->repository->list();
    }

    /**
     * @return array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}
     */
    public function create(string $namespace): array
    {
        $normalized = $this->normalizeNamespace($namespace);
        $this->assertValidNamespace($normalized);

        if ($this->repository->exists($normalized)) {
            throw new DuplicateNamespaceException('namespace-exists');
        }

        $this->repository->create($normalized);

        return $this->repository->find($normalized) ?? [
            'namespace' => $normalized,
            'label' => null,
            'is_active' => true,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @return array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}
     */
    public function rename(string $namespace, string $newNamespace): array
    {
        $source = $this->normalizeNamespace($namespace);
        $target = $this->normalizeNamespace($newNamespace);

        if ($source === PageService::DEFAULT_NAMESPACE) {
            throw new InvalidArgumentException('default-namespace');
        }

        $this->assertValidNamespace($target);

        if (!$this->repository->exists($source)) {
            throw new NamespaceNotFoundException('namespace-missing');
        }

        if ($source !== $target && $this->repository->exists($target)) {
            throw new DuplicateNamespaceException('namespace-exists');
        }

        $this->repository->update($source, $target);

        return $this->repository->find($target) ?? [
            'namespace' => $target,
            'label' => null,
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
        $normalized = $this->normalizeNamespace($namespace);

        if ($normalized === PageService::DEFAULT_NAMESPACE) {
            throw new InvalidArgumentException('default-namespace');
        }

        if (!$this->repository->exists($normalized)) {
            throw new NamespaceNotFoundException('namespace-missing');
        }

        $this->repository->delete($normalized);
    }

    private function normalizeNamespace(string $namespace): string
    {
        return strtolower(trim($namespace));
    }

    private function assertValidNamespace(string $namespace): void
    {
        if ($namespace === '') {
            throw new InvalidArgumentException('namespace-empty');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $namespace)) {
            throw new InvalidArgumentException('namespace-invalid');
        }
    }
}
