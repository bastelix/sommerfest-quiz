<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Security\TurnstileConfig;
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

        $turnstileSiteKey = TurnstileConfig::isEnabled() ? TurnstileConfig::getSiteKey() : null;

        if (!MailService::isConfigured()) {
            $html = preg_replace(
                '/<form id="contact-form"[\s\S]*?<\/form>/',
                '<p class="uk-text-center">Kontaktformular derzeit nicht verfügbar.</p>',
                $html
            );
        } elseif ($turnstileSiteKey !== null) {
            $html = $this->injectTurnstileWidget($html, $turnstileSiteKey);
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
            'turnstileSiteKey' => $turnstileSiteKey,
        ];

        if ($canonicalUrl !== null) {
            $data['hreflangLinks'] = $this->buildHreflangLinks($config?->getHreflang(), $canonicalUrl);
        }

        try {
            return $view->render($response, $template, $data);
        } catch (LoaderError $e) {
            return $response->withStatus(404);
        }
    }

    private function injectTurnstileWidget(string $html, string $siteKey): string
    {
        $widget = sprintf(
            "\n          <div class=\"cf-turnstile\" data-sitekey=\"%s\" data-theme=\"auto\"></div>\n",
            htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8')
        );

        $replaced = preg_replace_callback(
            '/(<form\s+id="contact-form"[^>]*>)([\s\S]*?)(<\/form>)/i',
            static function (array $matches) use ($widget): string {
                if (str_contains($matches[0], 'cf-turnstile')) {
                    return $matches[0];
                }

                return $matches[1] . $matches[2] . $widget . $matches[3];
            },
            $html,
            1,
            $count
        );

        if ($count > 0 && $replaced !== null) {
            return $replaced;
        }

        return $html;
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
