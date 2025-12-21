<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DuplicateNamespaceException;
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
        return $this->repository->list();
    }

    /**
     * @return array{namespace:string,label:?string,is_active:bool,created_at:?string,updated_at:?string}
     */
    public function create(string $namespace): array
    {
        $normalized = $this->validator->normalize($namespace);
        $this->validator->assertValid($normalized);

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
        $source = $this->validator->normalize($namespace);
        $target = $this->validator->normalize($newNamespace);

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
        $normalized = $this->validator->normalize($namespace);

        if ($normalized === PageService::DEFAULT_NAMESPACE) {
            throw new InvalidArgumentException('default-namespace');
        }

        if (!$this->repository->exists($normalized)) {
            throw new NamespaceNotFoundException('namespace-missing');
        }

        $this->repository->delete($normalized);
    }

}
