<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\ConfigService;
use App\Infrastructure\Database;

/**
 * Presents the help page with configuration settings.
 */
class HelpController
{
    /**
     * Render the help view.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $pdo = Database::connectFromEnv();
        $cfg = (new ConfigService($pdo))->getConfig();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['admin'])) {
            $cfg = ConfigService::removePuzzleInfo($cfg);
        }

        if (!empty($cfg['inviteText'])) {
            $cfg['inviteText'] = str_ireplace('[team]', 'Team', (string)$cfg['inviteText']);
        }

        return $view->render($response, 'help.twig', ['config' => $cfg]);
    }
}
