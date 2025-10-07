<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Service\LandingNewsService;
use App\Service\MailService;
use App\Service\MarketingSlugResolver;
use App\Service\PageService;
use App\Service\ProvenExpertRatingService;
use App\Service\TurnstileConfig;
use App\Support\BasePathHelper;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Twig\Error\LoaderError;

use function html_entity_decode;
use function htmlspecialchars;
use function preg_replace;

class MarketingPageController
{
    private const CALHELP_NEWS_PLACEHOLDER = '__CALHELP_NEWS_SECTION__';

    private const DEFAULT_NEWSLETTER_BRAND = 'QuizRace';

    /** @var array<string, string> */
    private const NEWSLETTER_BRANDS = [
        'landing' => 'QuizRace',
        'calserver' => 'calServer',
        'calhelp' => 'calHelp',
        'future-is-green' => 'Future is Green',
    ];

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
        $html = str_replace('data-calserver-chat-open', 'data-marketing-chat-open', $html);
        $html = str_replace('aria-controls="calserver-chat-modal"', 'aria-controls="marketing-chat-modal"', $html);

        $newsReplacement = $this->replaceCalhelpNewsSection($html, self::CALHELP_NEWS_PLACEHOLDER);
        $html = $newsReplacement['html'];

        $moduleExtraction = $this->extractCalhelpModules($html);
        $html = $moduleExtraction['html'];
        $calhelpModules = $moduleExtraction['data'];

        $usecaseExtraction = $this->extractCalhelpUsecases($html);
        $html = $usecaseExtraction['html'];
        $calhelpUsecases = $usecaseExtraction['data'];

        $calhelpNewsPlaceholderActive = $newsReplacement['replaced'];

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
            $html = $this->ensureNewsletterOptIn($html, $templateSlug, $locale);
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

        $chatPath = sprintf('/%s/chat', $templateSlug);
        if (str_starts_with($request->getUri()->getPath(), '/m/')) {
            $chatPath = sprintf('/m/%s/chat', $templateSlug);
        }

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
            'csrf_token' => $csrf,
            'marketingSlug' => $templateSlug,
            'marketingChatEndpoint' => $basePath . $chatPath,
        ];

        if ($calhelpModules !== null && ($calhelpModules['modules'] ?? []) !== []) {
            $data['calhelpModules'] = $calhelpModules;
        }

        if ($calhelpUsecases !== null && ($calhelpUsecases['usecases'] ?? []) !== []) {
            $data['calhelpUsecases'] = $calhelpUsecases;
        }

        if ($landingNews !== []) {
            $data['landingNews'] = $landingNews;
            $data['landingNewsBasePath'] = $landingNewsBasePath;
            $data['landingNewsIndexUrl'] = $basePath . $landingNewsBasePath;
        }

        $data['calhelpNewsPlaceholder'] = $calhelpNewsPlaceholderActive ? self::CALHELP_NEWS_PLACEHOLDER : null;
        $data['calhelpNewsPlaceholderActive'] = $calhelpNewsPlaceholderActive;

        if (in_array($templateSlug, ['calserver', 'landing'], true)) {
            $data['provenExpertRating'] = $this->provenExpert->getAggregateRatingMarkup();
        }

        if ($canonicalUrl !== null) {
            $data['hreflangLinks'] = $this->buildHreflangLinks($config?->getHreflang(), $canonicalUrl);
        }

        if ($templateSlug === 'calserver-maintenance') {
            $data['maintenanceWindowLabel'] = $this->buildMaintenanceWindowLabel($locale);
        }

        try {
            return $view->render($response, $template, $data);
        } catch (LoaderError $e) {
            return $response->withStatus(404);
        }
    }

    private function buildMaintenanceWindowLabel(string $locale): string
    {
        $timezone = new DateTimeZone('Europe/Berlin');
        $start = new DateTimeImmutable('today', $timezone);
        $end = $start->modify('+2 days');

        $intlLocale = $locale === 'en' ? 'en_GB' : 'de_DE';
        $sameMonth = $start->format('mY') === $end->format('mY');

        if ($locale === 'en') {
            $startPattern = $sameMonth ? 'd' : 'd MMMM';
            $startFallback = $sameMonth ? 'j' : 'j F';
            $endPattern = 'd MMMM';
            $endFallback = 'j F';
        } else {
            $startPattern = $sameMonth ? 'd.' : 'd. MMMM';
            $startFallback = $sameMonth ? 'j.' : 'j. F';
            $endPattern = 'd. MMMM';
            $endFallback = 'j. F';
        }

        $startLabel = $this->formatWithIntl($start, $intlLocale, $timezone, $startPattern, $startFallback);
        $endLabel = $this->formatWithIntl($end, $intlLocale, $timezone, $endPattern, $endFallback);

        return sprintf('%s–%s', $startLabel, $endLabel);
    }

    private function formatWithIntl(
        DateTimeImmutable $date,
        string $locale,
        DateTimeZone $timezone,
        string $pattern,
        string $fallbackPattern
    ): string {
        if (class_exists('\\IntlDateFormatter')) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                $timezone->getName(),
                IntlDateFormatter::GREGORIAN,
                $pattern
            );

            $formatted = $formatter->format($date);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $date->format($fallbackPattern);
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

    private function ensureNewsletterOptIn(string $html, string $templateSlug, string $locale): string
    {
        if (!str_contains($html, 'id="contact-form"')) {
            return $html;
        }

        if (str_contains($html, 'name="newsletter_subscribe"') || str_contains($html, 'name="newsletter_action"')) {
            return $html;
        }

        $formPattern = '/(<form[^>]*id="contact-form"[^>]*>)(.*?)(<\/form>)/is';
        if (!preg_match($formPattern, $html, $matches)) {
            return $html;
        }

        $formContent = $matches[2];
        if (str_contains($formContent, 'newsletter_subscribe')) {
            return $html;
        }

        $label = $this->determineNewsletterLabel($html, $templateSlug, $locale);
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES);

        $newsletterMarkup = <<<HTML
          <div class="uk-margin">
            <label><input class="uk-checkbox" type="checkbox" name="newsletter_subscribe" value="1"> {$escapedLabel}</label>
          </div>
        HTML;

        $privacyPattern = '/(<div[^>]*>\s*<label[^>]*><input[^>]*name="privacy"[^>]*>.*?<\/label>\s*<\/div>)/is';
        $applied = false;
        $updatedFormContent = preg_replace_callback(
            $privacyPattern,
            static function (array $innerMatches) use ($newsletterMarkup, &$applied): string {
                if ($applied) {
                    return $innerMatches[0];
                }
                $applied = true;

                return $newsletterMarkup . "\n" . $innerMatches[0];
            },
            $formContent,
            1
        );

        if (is_string($updatedFormContent) && $updatedFormContent !== $formContent) {
            $formContent = $updatedFormContent;
        } else {
            $formContent = $newsletterMarkup . $formContent;
        }

        $replacement = $matches[1] . $formContent . $matches[3];
        $result = preg_replace($formPattern, $replacement, $html, 1);

        return is_string($result) ? $result : $html;
    }

    private function determineNewsletterLabel(string $html, string $templateSlug, string $locale): string
    {
        $formTag = null;
        if (preg_match('/<form[^>]*id="contact-form"[^>]*>/i', $html, $formMatches)) {
            $formTag = $formMatches[0];
        }

        if (is_string($formTag) && preg_match('/data-newsletter-label="([^\"]+)"/i', $formTag, $labelMatches)) {
            return html_entity_decode($labelMatches[1], ENT_QUOTES);
        }

        $brand = null;
        if (is_string($formTag) && preg_match('/data-newsletter-brand="([^\"]+)"/i', $formTag, $brandMatches)) {
            $brand = html_entity_decode($brandMatches[1], ENT_QUOTES);
        }

        if ($brand === null) {
            $brand = $this->resolveNewsletterBrand($html, $templateSlug);
        }

        return $this->buildNewsletterLabel($brand, $locale);
    }

    private function resolveNewsletterBrand(string $html, string $templateSlug): string
    {
        $identifier = $this->extractContactEndpointSlug($html);
        if ($identifier === null) {
            $identifier = $templateSlug;
        }

        $normalized = $this->normalizeNewsletterSlug($identifier);
        if (isset(self::NEWSLETTER_BRANDS[$normalized])) {
            return self::NEWSLETTER_BRANDS[$normalized];
        }

        if (str_contains($normalized, 'calserver')) {
            return self::NEWSLETTER_BRANDS['calserver'];
        }

        if (str_contains($normalized, 'calhelp')) {
            return self::NEWSLETTER_BRANDS['calhelp'];
        }

        if (str_contains($normalized, 'future-is-green') || str_contains($normalized, 'futureisgreen')) {
            return self::NEWSLETTER_BRANDS['future-is-green'];
        }

        return self::DEFAULT_NEWSLETTER_BRAND;
    }

    private function extractContactEndpointSlug(string $html): ?string
    {
        if (preg_match('/data-contact-endpoint="[^"]*\/([a-z0-9-]+)\/contact"/i', $html, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function normalizeNewsletterSlug(string $slug): string
    {
        $normalized = strtolower($slug);
        if (preg_match('/^(.*)-(de|en)$/', $normalized, $matches)) {
            return $matches[1];
        }

        return $normalized;
    }

    private function buildNewsletterLabel(?string $brand, string $locale): string
    {
        $normalizedBrand = trim((string) $brand);
        if ($normalizedBrand === '') {
            $normalizedBrand = self::DEFAULT_NEWSLETTER_BRAND;
        }

        $language = strtolower(substr($locale, 0, 2));
        if ($language === 'en') {
            return sprintf('I would like to receive the %s newsletter.', $normalizedBrand);
        }

        return sprintf('Ich möchte den %s Newsletter erhalten.', $normalizedBrand);
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

    /**
     * @return array{html: string, data: array|null}
     */
    private function extractCalhelpModules(string $html): array
    {
        $pattern = '/<script[^>]*data-calhelp-modules[^>]*>(.*?)<\/script>/si';
        if (!preg_match($pattern, $html, $matches)) {
            return ['html' => $html, 'data' => null];
        }

        $json = trim(html_entity_decode($matches[1], ENT_QUOTES));
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return ['html' => str_replace($matches[0], '', $html), 'data' => null];
        }

        $modules = [];
        if (isset($decoded['modules']) && is_array($decoded['modules'])) {
            foreach ($decoded['modules'] as $module) {
                if (is_array($module)) {
                    $modules[] = $module;
                }
            }
        }

        $data = [
            'headline' => isset($decoded['headline']) && is_array($decoded['headline']) ? $decoded['headline'] : [],
            'subheadline' => isset($decoded['subheadline']) && is_array($decoded['subheadline']) ? $decoded['subheadline'] : [],
            'modules' => $modules,
        ];

        if (isset($decoded['eyebrow']) && is_array($decoded['eyebrow'])) {
            $data['eyebrow'] = $decoded['eyebrow'];
        }

        return [
            'html' => str_replace($matches[0], '', $html),
            'data' => $data,
        ];
    }

    /**
     * @return array{html: string, data: array|null}
     */
    private function extractCalhelpUsecases(string $html): array
    {
        $pattern = '/<script[^>]*data-calhelp-usecases[^>]*>(.*?)<\/script>/si';
        if (!preg_match($pattern, $html, $matches)) {
            return ['html' => $html, 'data' => null];
        }

        $json = trim(html_entity_decode($matches[1], ENT_QUOTES));
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return ['html' => str_replace($matches[0], '', $html), 'data' => null];
        }

        $usecases = [];
        if (isset($decoded['usecases']) && is_array($decoded['usecases'])) {
            foreach ($decoded['usecases'] as $usecase) {
                if (is_array($usecase)) {
                    $usecases[] = $usecase;
                }
            }
        }

        $data = ['usecases' => $usecases];

        return [
            'html' => str_replace($matches[0], '', $html),
            'data' => $data,
        ];
    }

    /**
     * Replace the static calHelp news section with a placeholder for dynamic rendering.
     *
     * @return array{html: string, replaced: bool}
     */
    private function replaceCalhelpNewsSection(string $html, string $placeholder): array
    {
        $pattern = '/<section id="news" class="uk-section calhelp-section" aria-labelledby="news-title">[\s\S]*?<\/section>/i';

        $result = preg_replace($pattern, $placeholder, $html, 1, $count);

        if (!is_string($result) || $count === 0) {
            return ['html' => $html, 'replaced' => false];
        }

        return ['html' => $result, 'replaced' => true];
    }

    private function resolveLocalizedSlug(string $baseSlug, string $locale): string {
        return MarketingSlugResolver::resolveLocalizedSlug($baseSlug, $locale);
    }
}
