<?php

declare(strict_types=1);

namespace Tests;

use App\Service\NamespaceService;
use App\Service\NamespaceResolver;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use ReflectionProperty;
use RuntimeException;

final class NamespaceResolverTest extends TestCase
{
    public function testResolvesExplicitNamespaceWhenItExists(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://quizrace.app/admin/dashboard')
            ->withAttribute('namespace', 'calserver');

        $resolver = new NamespaceResolver();

        $namespaceService = $this->createMock(NamespaceService::class);
        $namespaceService->method('exists')->with('calserver')->willReturn(true);

        $namespaceServiceProperty = new ReflectionProperty(NamespaceResolver::class, 'namespaceService');
        $namespaceServiceProperty->setAccessible(true);
        $namespaceServiceProperty->setValue($resolver, $namespaceService);

        $context = $resolver->resolve($request);

        self::assertSame('calserver', $context->getNamespace());
        self::assertSame(['calserver'], $context->getCandidates());
        self::assertFalse($context->usedFallback());
    }

    public function testThrowsWhenNamespaceDoesNotExist(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://quizrace.app/admin/dashboard')
            ->withAttribute('namespace', 'missing');

        $resolver = new NamespaceResolver();

        $namespaceService = $this->createMock(NamespaceService::class);
        $namespaceService->method('exists')->with('missing')->willReturn(false);

        $namespaceServiceProperty = new ReflectionProperty(NamespaceResolver::class, 'namespaceService');
        $namespaceServiceProperty->setAccessible(true);
        $namespaceServiceProperty->setValue($resolver, $namespaceService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid namespace candidate available.');

        $resolver->resolve($request);
    }

    public function testThrowsWhenNoNamespaceCandidatesAreProvided(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://quizrace.app/');
        $resolver = new NamespaceResolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Namespace could not be resolved for request.');

        $resolver->resolve($request);
    }
}
