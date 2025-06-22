<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\ConfigService;
use Slim\Views\Twig;

/**
 * Shows the overall quiz summary page.
 */
class SummaryController
{
    private ConfigService $config;

    /**
     * Inject configuration service dependency.
     */
    public function __construct(ConfigService $config)
    {
        $this->config = $config;
    }

    /**
     * Render the summary page.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $cfg = $this->config->getConfig();
        return $view->render($response, 'summary.twig', ['config' => $cfg]);
    }
}
