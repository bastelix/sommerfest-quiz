<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Security\Captcha\ContactCaptchaConfig;
use App\Application\Seo\PageSeoConfigService;
use App\Service\MailService;
use App\Service\PageService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Twig\Error\LoaderError;

class MarketingPageController
{
    private PageService $pages;
    private PageSeoConfigService $seo;
    private ?string $slug;

    public function __construct(?string $slug = null, ?PageService $pages = null, ?PageSeoConfigService $seo = null)
    {
        $this->slug = $slug;
        $this->pages = $pages ?? new PageService();
        $this->seo = $seo ?? new PageSeoConfigService();
    }

    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        $slug = $this->slug ?? (string) ($args['slug'] ?? '');
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            return $response->withStatus(404);
        }

        $page = $this->pages->findBySlug($slug);
        if ($page === null) {
            return $response->withStatus(404);
        }

        $html = $page->getContent();
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $html = str_replace('{{ csrf_token }}', $csrf, $html);

        $captchaConfig = ContactCaptchaConfig::fromEnv();
        $html = str_replace('{{ contact_captcha_provider }}', $captchaConfig->isEnabled() ? $captchaConfig->getProvider() : '', $html);
        $html = str_replace('{{ contact_captcha_sitekey }}', $captchaConfig->getSiteKey() ?? '', $html);

        if (!MailService::isConfigured()) {
            $html = preg_replace(
                '/<form id="contact-form"[\s\S]*?<\/form>/',
                '<p class="uk-text-center">Kontaktformular derzeit nicht verfügbar.</p>',
                $html
            );
        }

        $view = Twig::fromRequest($request);
        $template = sprintf('marketing/%s.twig', $slug);
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
        ];

        if ($canonicalUrl !== null) {
            $data['hreflangLinks'] = $this->buildHreflangLinks($config?->getHreflang(), $canonicalUrl);
        }

        $data['contactCaptcha'] = [
            'provider' => $captchaConfig->isEnabled() ? $captchaConfig->getProvider() : null,
            'siteKey' => $captchaConfig->isEnabled() ? $captchaConfig->getSiteKey() : null,
        ];

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
}
