<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LogService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AdminLogsController
{
    /**
     * Display recent application logs.
     */
    public function __invoke(Request $request, Response $response): Response {
        $appLog = LogService::tail('app');
        $stripeLog = LogService::tail('stripe');
        $slimLog = LogService::tailDocker('slim-1');
        $view = Twig::fromRequest($request);
        $role = $_SESSION['user']['role'] ?? '';
        return $view->render($response, 'admin/logs.twig', [
            'appLog' => $appLog,
            'stripeLog' => $stripeLog,
            'slimLog' => $slimLog,
            'role' => $role,
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
        ]);
    }
}
