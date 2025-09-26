<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Application\Seo\PageSeoConfigService;
use App\Service\MailService;
use App\Service\PageService;
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
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $html = str_replace('{{ basePath }}', $basePath, $html);

        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $html = str_replace('{{ csrf_token }}', $csrf, $html);

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
        if (method_exists($loader, 'exists') && !$loader->exists($template)) {
            return $response->withStatus(404);
        }

        $config = $this->seo->load($page->getId());
        try {
            return $view->render($response, $template, [
                'content' => $html,
                'pageFavicon' => $config?->getFaviconPath(),
            ]);
        } catch (LoaderError $e) {
            return $response->withStatus(404);
        }
    }
}
