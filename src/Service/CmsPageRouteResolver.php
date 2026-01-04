<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\Marketing\LandingController;
use App\Controller\Cms\PageController;
use App\Domain\Page;
use App\Service\MarketingSlugResolver;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CmsPageRouteResolver
{
    /** @var array<string, class-string> */
    private const CONTROLLER_MAP = [
        'landing' => LandingController::class,
    ];

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
        $normalized = trim($slug);
        if ($normalized === '' || !preg_match('/^[a-z0-9-]+$/', $normalized)) {
            return null;
        }

        $page = $this->resolvePage($request, $normalized);
        if ($page === null) {
            return null;
        }

        $controllerKey = $page->getType();
        if ($controllerKey !== null && $controllerKey !== '' && isset(self::CONTROLLER_MAP[$controllerKey])) {
            $controllerClass = self::CONTROLLER_MAP[$controllerKey];
            return new $controllerClass($this->pages);
        }

        return new PageController($normalized, $this->pages);
    }

    private function resolvePage(Request $request, string $slug): ?Page {
        $locale = (string) $request->getAttribute('lang');
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $contentSlug = MarketingSlugResolver::resolveLocalizedSlug($slug, $locale);

        $page = $this->pages->findByKey($namespace, $contentSlug);
        if ($page === null && $contentSlug !== $slug) {
            $page = $this->pages->findByKey($namespace, $slug);
        }

        if ($page === null && $namespace !== PageService::DEFAULT_NAMESPACE) {
            $fallbackNamespace = PageService::DEFAULT_NAMESPACE;
            $page = $this->pages->findByKey($fallbackNamespace, $contentSlug);
            if ($page === null && $contentSlug !== $slug) {
                $page = $this->pages->findByKey($fallbackNamespace, $slug);
            }
        }

        return $page;
    }
}
