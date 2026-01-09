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
    private PageService $pages;
    private NamespaceResolver $namespaceResolver;

    public function __construct(?PageService $pages = null, ?NamespaceResolver $namespaceResolver = null) {
        $this->pages = $pages ?? new PageService();
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
        $contentSlug = MarketingSlugResolver::resolveLocalizedSlug($slug, $locale);

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        $pages = new PageService($pdo);
        $page = $pages->findByKey($namespace, $contentSlug);
        if ($page === null && $contentSlug !== $slug) {
            $page = $pages->findByKey($namespace, $slug);
        }

        if ($page === null && $namespace !== PageService::DEFAULT_NAMESPACE) {
            $fallbackNamespace = PageService::DEFAULT_NAMESPACE;
            $page = $pages->findByKey($fallbackNamespace, $contentSlug);
            if ($page === null && $contentSlug !== $slug) {
                $page = $pages->findByKey($fallbackNamespace, $slug);
            }
        }

        return $page;
    }
}
