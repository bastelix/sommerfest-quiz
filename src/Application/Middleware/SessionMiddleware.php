<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Service\SessionService;
use App\Infrastructure\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class SessionMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $response = $handler->handle($request);

        if (isset($_SESSION['user']['id'])) {
            try {
                $pdo = $request->getAttribute('pdo');
                if (!$pdo instanceof PDO) {
                    $pdo = Database::connectFromEnv();
                }
                $service = new SessionService($pdo);
                $service->persistSession((int) $_SESSION['user']['id'], session_id());
            } catch (\Throwable $e) {
                // Ignore persistence errors to avoid breaking the request flow.
            }
        }

        return $response;
    }
}
