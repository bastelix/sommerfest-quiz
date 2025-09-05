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
        $cookies = $request->getCookieParams();
        $hasSessionCookie = isset($cookies[session_name()]);

        if (session_status() === PHP_SESSION_ACTIVE && !$hasSessionCookie) {
            session_unset();
            session_destroy();
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $host = $request->getUri()->getHost();
            $domain = getenv('MAIN_DOMAIN') ?: '';
            if ($domain === '' || !$this->hostMatchesDomain($host, $domain)) {
                $domain = $host;
            }

            if ($domain !== '') {
                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                session_set_cookie_params([
                    'domain' => '.' . ltrim($domain, '.'),
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

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

    private function hostMatchesDomain(string $host, string $domain): bool
    {
        $domain = ltrim($domain, '.');

        return strcasecmp($host, $domain) === 0
            || str_ends_with($host, '.' . $domain);
    }
}
