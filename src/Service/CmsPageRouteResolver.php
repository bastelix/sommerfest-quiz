<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\Marketing\PageController;
use App\Domain\Page;
use App\Infrastructure\Database;
use App\Service\MarketingSlugResolver;
use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CmsPageRouteResolver
{
    private NamespaceResolver $namespaceResolver;

    public function __construct(?NamespaceResolver $namespaceResolver = null) {
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    /**
     * Resolve a marketing slug to the corresponding controller.
     */
    public function resolveController(Request $request, string $slug): ?callable {
        if ($request->getAttribute('domainType') !== 'marketing') {
            return null;
        }

        $normalized = trim($slug);
        if ($normalized === '' || !preg_match('/^[a-z0-9-]+$/', $normalized)) {
            return null;
        }

        $page = $this->resolvePage($request, $normalized);
        if ($page === null) {
            return null;
        }

        return new PageController($page->getSlug());
    }

    private function resolvePage(Request $request, string $slug): ?Page {
        $locale = (string) $request->getAttribute('lang');
        $namespace = (string) ($request->getAttribute('pageNamespace') ?? $request->getAttribute('namespace') ?? '');

        if ($namespace === '') {
            $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        }
        // 1. Try hardcoded slug map
        $contentSlug = MarketingSlugResolver::resolveLocalizedSlug($slug, $locale);

        $pdo = \App\Support\RequestDatabase::resolve($request);

        $pages = new PageService($pdo);
        $page = $pages->findByKey($namespace, $contentSlug);
        if ($page === null && $contentSlug !== $slug) {
            $page = $pages->findByKey($namespace, $slug);
        }

        // 2. Try dynamic DB resolution via base_slug column
        if ($page === null || ($contentSlug === $slug && $locale !== '' && $locale !== 'de')) {
            $dbSlug = MarketingSlugResolver::resolveFromDatabase($pdo, $namespace, $slug, $locale);
            if ($dbSlug !== null) {
                $dbPage = $pages->findByKey($namespace, $dbSlug);
                if ($dbPage !== null) {
                    $page = $dbPage;
                }
            }
        }

        // 3. Fallback to default namespace
        if ($page === null && $namespace !== PageService::DEFAULT_NAMESPACE) {
            $fallbackNamespace = PageService::DEFAULT_NAMESPACE;
            $page = $pages->findByKey($fallbackNamespace, $contentSlug);
            if ($page === null && $contentSlug !== $slug) {
                $page = $pages->findByKey($fallbackNamespace, $slug);
            }
            // Also try dynamic DB resolution in default namespace
            if ($page === null && $locale !== '' && $locale !== 'de') {
                $dbSlug = MarketingSlugResolver::resolveFromDatabase($pdo, $fallbackNamespace, $slug, $locale);
                if ($dbSlug !== null) {
                    $page = $pages->findByKey($fallbackNamespace, $dbSlug);
                }
            }
        }

        return $page;
    }
}
