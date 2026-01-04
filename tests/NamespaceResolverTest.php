<?php

declare(strict_types=1);

namespace Tests;

use App\Service\NamespaceResolver;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use ReflectionProperty;

final class NamespaceResolverTest extends TestCase
{
    public function testPrefersActiveNamespaceFromSessionAttributeWhenQueryMissing(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://quizrace.app/admin/dashboard')
            ->withAttribute('active_namespace', 'calserver')
            ->withAttribute('domainType', 'main');

        $resolver = new NamespaceResolver();
        $context = $resolver->resolve($request);

        self::assertSame('calserver', $context->getNamespace());
    }

    public function testFallsBackToDefaultNamespaceWhenNoCandidatesPresent(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://quizrace.app/');
        $resolver = new NamespaceResolver();

        $namespaceService = new class() {
            /** @var list<string> */
            public array $created = [];

            public function exists(string $namespace): bool
            {
                return false;
            }

            public function create(string $namespace): void
            {
                $this->created[] = $namespace;
            }
        };

        $namespaceServiceProperty = new ReflectionProperty(NamespaceResolver::class, 'namespaceService');
        $namespaceServiceProperty->setAccessible(true);
        $namespaceServiceProperty->setValue($resolver, $namespaceService);

        $context = $resolver->resolve($request);

        self::assertSame('default', $context->getNamespace());
        self::assertSame(['default'], $context->getCandidates());
        self::assertSame(['default'], $namespaceService->created);
    }
}
