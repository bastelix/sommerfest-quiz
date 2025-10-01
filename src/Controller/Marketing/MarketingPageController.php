<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Service\LandingNewsService;
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
    private LandingNewsService $landingNews;

    public function __construct(
        ?string $slug = null,
        ?PageService $pages = null,
        ?PageSeoConfigService $seo = null,
        ?TurnstileConfig $turnstileConfig = null,
        ?ProvenExpertRatingService $provenExpert = null,
        ?LandingNewsService $landingNews = null
    ) {
        $this->slug = $slug;
        $this->pages = $pages ?? new PageService();
        $this->seo = $seo ?? new PageSeoConfigService();
        $this->turnstileConfig = $turnstileConfig ?? TurnstileConfig::fromEnv();
        $this->provenExpert = $provenExpert ?? new ProvenExpertRatingService();
        $this->landingNews = $landingNews ?? new LandingNewsService();
    }

    public function __invoke(Request $request, Response $response, array $args = []): Response {
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

        $landingNews = $this->landingNews->getPublishedForPage($page->getId(), 3);
        $landingNewsBasePath = null;
        if ($landingNews !== []) {
            $landingNewsBasePath = $this->buildNewsBasePath($request, $page->getSlug());
        }

        $mailConfigured = MailService::isConfigured();
        $placeholderToken = '{{ turnstile_widget }}';
        $hadPlaceholder = str_contains($html, $placeholderToken);

        $widgetMarkup = '';
        if ($this->turnstileConfig->isEnabled()) {
            $siteKey = $this->turnstileConfig->getSiteKey() ?? '';
            $widgetMarkup = sprintf(
                '<div class="cf-turnstile" data-sitekey="%s" data-callback="contactTurnstileSuccess" ' .
                'data-error-callback="contactTurnstileError" data-expired-callback="contactTurnstileExpired"></div>',
                htmlspecialchars($siteKey, ENT_QUOTES)
            );
        }
        $html = str_replace($placeholderToken, $widgetMarkup, $html);

        if (!$mailConfigured) {
            $html = preg_replace(
                '/<form id="contact-form"[\s\S]*?<\/form>/',
                '<p class="uk-text-center">Kontaktformular derzeit nicht verfügbar.</p>',
                $html
            );
        } else {
            $html = $this->ensureTurnstileMarkup($html, $widgetMarkup, $hadPlaceholder);
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

        if ($landingNews !== []) {
            $data['landingNews'] = $landingNews;
            $data['landingNewsBasePath'] = $landingNewsBasePath;
            $data['landingNewsIndexUrl'] = $landingNewsBasePath !== null ? $basePath . $landingNewsBasePath : null;
        }

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
     * Ensure the marketing contact form contains the Turnstile widget markup when enabled.
     *
     * @param string $html               Rendered page content
     * @param string $widgetMarkup       Prepared Turnstile widget markup
     * @param bool   $placeholderExisted True when the original template provided a placeholder
     *
     * @return string Updated page content with widget markup inserted when necessary
     */
    private function ensureTurnstileMarkup(string $html, string $widgetMarkup, bool $placeholderExisted): string
    {
        if ($widgetMarkup === '' || $placeholderExisted) {
            return $html;
        }

        if (str_contains($html, 'cf-turnstile')) {
            return $html;
        }

        $formPattern = '/(<form[^>]*id="contact-form"[^>]*>)(.*?)(<\/form>)/is';
        if (!preg_match($formPattern, $html, $matches)) {
            return $html;
        }

        $formContent = $matches[2];
        if (str_contains($formContent, 'cf-turnstile')) {
            return $html;
        }

        $containerPattern = '/(<div[^>]*data-turnstile-container[^>]*>)(.*?)(<\/div>)/is';
        $updatedFormContent = $formContent;

        if (preg_match($containerPattern, $formContent, $containerMatches)) {
            if (!str_contains($containerMatches[2], 'cf-turnstile')) {
                $containerReplacement = $containerMatches[1]
                    . $containerMatches[2]
                    . $widgetMarkup
                    . $containerMatches[3];
                $updatedFormContent = preg_replace(
                    $containerPattern,
                    $containerReplacement,
                    $formContent,
                    1
                ) ?? $formContent;
            }
        } else {
            $updatedFormContent .= '<div class="turnstile-widget">' . $widgetMarkup . '</div>';
        }

        $replacement = $matches[1] . $updatedFormContent . $matches[3];
        $result = preg_replace($formPattern, $replacement, $html, 1);

        return is_string($result) ? $result : $html;
    }

    /**
     * Normalize hreflang definitions to a list of alternate link descriptors.
     *
     * @return array<int,array{href:string,hreflang:string}>
     */
    private function buildHreflangLinks(?string $hreflang, string $canonicalUrl): array {
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

    private function buildNewsBasePath(Request $request, string $pageSlug): string
    {
        $path = $request->getUri()->getPath();
        if (preg_match('~^/m/([a-z0-9-]+)~', $path) === 1) {
            return sprintf('/m/%s/news', $pageSlug);
        }

        return sprintf('/%s/news', $pageSlug);
    }

    private function resolveLocalizedSlug(string $baseSlug, string $locale): string {
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
