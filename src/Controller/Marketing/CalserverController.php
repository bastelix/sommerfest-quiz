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

/**
 * Displays the calServer marketing preview page.
 */
class CalserverController
{
    private PageService $pages;
    private PageSeoConfigService $seo;

    public function __construct(?PageService $pages = null, ?PageSeoConfigService $seo = null)
    {
        $this->pages = $pages ?? new PageService();
        $this->seo = $seo ?? new PageSeoConfigService();
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $page = $this->pages->findBySlug('calserver');
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
        $config = $this->seo->load($page->getId());
        return $view->render($response, 'marketing/calserver.twig', [
            'content' => $html,
            'pageFavicon' => $config?->getFaviconPath(),
        ]);
    }
}
