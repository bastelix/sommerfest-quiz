<?php

declare(strict_types=1);

namespace Tests;

use App\Service\NamespaceResolver;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

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
}
