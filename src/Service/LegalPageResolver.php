<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Http\Message\ServerRequestInterface as Request;

class LegalPageResolver
{
    private PageService $pages;
    private NamespaceResolver $namespaceResolver;

    public function __construct(?PageService $pages = null, ?NamespaceResolver $namespaceResolver = null)
    {
        $this->pages = $pages ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    public function resolve(Request $request, string $slug): ?string
    {
        $normalizedSlug = trim($slug);
        if ($normalizedSlug === '') {
            return null;
        }

        $context = $this->namespaceResolver->resolve($request);
        $candidates = $context->getCandidates();
        foreach ($candidates as $namespace) {
            $content = $this->pages->getByKey($namespace, $normalizedSlug);
            if ($content !== null) {
                return $content;
            }
        }

        error_log(sprintf(
            'Legal page not found for slug "%s" (namespaces: %s)',
            $normalizedSlug,
            implode(', ', $candidates)
        ));

        return null;
    }
}
