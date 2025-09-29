<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Service\MailService;
use App\Service\PageService;
use App\Service\ProvenExpertRatingService;
use App\Service\TurnstileConfig;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Twig\Error\LoaderError;

use function htmlspecialchars;

class MarketingPageController
{
    private PageService $pages;
    private PageSeoConfigService $seo;
    private ?string $slug;
    private TurnstileConfig $turnstileConfig;
    private ProvenExpertRatingService $provenExpert;

    public function __construct(
        ?string $slug = null,
        ?PageService $pages = null,
        ?PageSeoConfigService $seo = null,
        ?TurnstileConfig $turnstileConfig = null,
        ?ProvenExpertRatingService $provenExpert = null
    )
    {
        $this->slug = $slug;
        $this->pages = $pages ?? new PageService();
        $this->seo = $seo ?? new PageSeoConfigService();
        $this->turnstileConfig = $turnstileConfig ?? TurnstileConfig::fromEnv();
        $this->provenExpert = $provenExpert ?? new ProvenExpertRatingService();
    }

    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        $templateSlug = $this->slug ?? (string) ($args['slug'] ?? '');
        if ($templateSlug === '' || !preg_match('/^[a-z0-9-]+$/', $templateSlug)) {
            return $response->withStatus(404);
        }

        $locale = (string) $request->getAttribute('lang');
        $contentSlug = $this->resolveLocalizedSlug($templateSlug, $locale);

        $page = $this->pages->findBySlug($contentSlug);
        if ($page === null && $contentSlug !== $templateSlug) {
            $page = $this->pages->findBySlug($templateSlug);
            $contentSlug = $templateSlug;
        }
        if ($page === null) {
            return $response->withStatus(404);
        }

        $html = $page->getContent();
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $html = str_replace('{{ csrf_token }}', $csrf, $html);

        $widgetMarkup = '';
        if ($this->turnstileConfig->isEnabled()) {
            $siteKey = $this->turnstileConfig->getSiteKey() ?? '';
            $widgetMarkup = sprintf(
                '<div class="cf-turnstile" data-sitekey="%s" data-callback="contactTurnstileSuccess" data-error-callback="contactTurnstileError" data-expired-callback="contactTurnstileExpired"></div>',
                htmlspecialchars($siteKey, ENT_QUOTES)
            );
        }
        $html = str_replace('{{ turnstile_widget }}', $widgetMarkup, $html);

        if (!MailService::isConfigured()) {
            $html = preg_replace(
                '/<form id="contact-form"[\s\S]*?<\/form>/',
                '<p class="uk-text-center">Kontaktformular derzeit nicht verfügbar.</p>',
                $html
            );
        }

        $view = Twig::fromRequest($request);
        $template = sprintf('marketing/%s.twig', $templateSlug);
        $loader = $view->getEnvironment()->getLoader();
        if (!$loader->exists($template)) {
            return $response->withStatus(404);
        }

        $config = $this->seo->load($page->getId());
        $globals = $view->getEnvironment()->getGlobals();
        $canonicalFallback = isset($globals['canonicalUrl']) ? (string) $globals['canonicalUrl'] : null;
        $canonicalUrl = $config?->getCanonicalUrl() ?? $canonicalFallback;

        $data = [
            'content' => $html,
            'pageFavicon' => $config?->getFaviconPath(),
            'metaTitle' => $config?->getMetaTitle(),
            'metaDescription' => $config?->getMetaDescription(),
            'canonicalUrl' => $canonicalUrl,
            'robotsMeta' => $config?->getRobotsMeta(),
            'ogTitle' => $config?->getOgTitle(),
            'ogDescription' => $config?->getOgDescription(),
            'ogImage' => $config?->getOgImage(),
            'schemaJson' => $config?->getSchemaJson(),
            'hreflang' => $config?->getHreflang(),
            'turnstileSiteKey' => $this->turnstileConfig->isEnabled() ? $this->turnstileConfig->getSiteKey() : null,
            'turnstileEnabled' => $this->turnstileConfig->isEnabled(),
        ];

        if (in_array($templateSlug, ['calserver', 'landing'], true)) {
            $data['provenExpertRating'] = $this->provenExpert->getAggregateRatingMarkup();
        }

        if ($canonicalUrl !== null) {
            $data['hreflangLinks'] = $this->buildHreflangLinks($config?->getHreflang(), $canonicalUrl);
        }

        try {
            return $view->render($response, $template, $data);
        } catch (LoaderError $e) {
            return $response->withStatus(404);
        }
    }

    /**
     * Normalize hreflang definitions to a list of alternate link descriptors.
     *
     * @return array<int,array{href:string,hreflang:string}>
     */
    private function buildHreflangLinks(?string $hreflang, string $canonicalUrl): array
    {
        if ($hreflang === null) {
            return [];
        }

        $hreflang = trim($hreflang);
        if ($hreflang === '') {
            return [];
        }

        $decoded = json_decode($hreflang, true);
        if (is_array($decoded)) {
            $links = [];
            foreach ($decoded as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $href = $entry['href'] ?? null;
                $lang = $entry['hreflang'] ?? null;
                if ($href === null || $lang === null) {
                    continue;
                }
                $href = trim((string) $href);
                $lang = trim((string) $lang);
                if ($href === '' || $lang === '') {
                    continue;
                }
                $links[] = [
                    'href' => $href,
                    'hreflang' => $lang,
                ];
            }
            if ($links !== []) {
                return $links;
            }
        }

        $codes = preg_split('/[\s,;|]+/', $hreflang, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($codes)) {
            return [];
        }

        $links = [];
        foreach ($codes as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            $links[] = [
                'href' => $canonicalUrl,
                'hreflang' => $code,
            ];
        }

        return $links;
    }

    private function resolveLocalizedSlug(string $baseSlug, string $locale): string
    {
        $locale = strtolower(trim($locale));
        if ($locale === '' || $locale === 'de') {
            return $baseSlug;
        }

        $map = [
            'calserver' => [
                'en' => 'calserver-en',
            ],
        ];

        if (isset($map[$baseSlug][$locale])) {
            return $map[$baseSlug][$locale];
        }

        return $baseSlug;
    }
}
