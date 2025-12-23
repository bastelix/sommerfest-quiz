<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Service\SessionService;
use App\Service\UserService;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceValidator;
use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Support\DomainNameHelper;
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
    public function process(Request $request, RequestHandler $handler): Response {
        $cookies = $request->getCookieParams();
        $hasSessionCookie = isset($cookies[session_name()]);

        if (session_status() === PHP_SESSION_ACTIVE && !$hasSessionCookie) {
            session_unset();
            session_destroy();
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $host = DomainNameHelper::normalize($request->getUri()->getHost());

            $domain = DomainNameHelper::normalize((string) getenv('MAIN_DOMAIN'));

            if ($domain === '' || !$this->hostMatchesDomain($host, $domain)) {
                $domain = $host;
            }

            $envSecure = getenv('SESSION_COOKIE_SECURE');
            if ($envSecure !== false) {
                $secure = filter_var($envSecure, FILTER_VALIDATE_BOOLEAN);
            } else {
                $proto = $request->getHeaderLine('X-Forwarded-Proto');
                if ($proto !== '') {
                    $secure = strtolower($proto) === 'https';
                } else {
                    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                }
            }
            $params = [
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            if (
                $domain !== '' &&
                str_contains($domain, '.') &&
                filter_var($domain, FILTER_VALIDATE_IP) === false
            ) {
                $params['domain'] = '.' . ltrim($domain, '.');
            }
            session_set_cookie_params($params);

            $sessionPath = $this->resolveSessionSavePath();
            if ($sessionPath !== null) {
                if (session_save_path() !== $sessionPath) {
                    session_save_path($sessionPath);
                }
                if (ini_get('session.save_path') !== $sessionPath) {
                    ini_set('session.save_path', $sessionPath);
                }
            }

            session_start();
        }

        $requestedNamespace = $this->resolveRequestedNamespace($request);
        if ($requestedNamespace !== null) {
            $this->applyRequestedNamespace($request, $requestedNamespace);
        }

        $activeNamespace = $this->resolveActiveNamespace($request);
        if ($activeNamespace !== null) {
            $request = $request
                ->withAttribute('active_namespace', $activeNamespace)
                ->withAttribute('namespace', $activeNamespace);
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

    private function hostMatchesDomain(string $host, string $domain): bool {
        $domain = ltrim($domain, '.');

        return strcasecmp($host, $domain) === 0
            || str_ends_with($host, '.' . $domain);
    }

    private function resolveSessionSavePath(): ?string {
        $candidates = [];

        $envPath = getenv('SESSION_SAVE_PATH');
        if ($envPath !== false && $envPath !== '') {
            $candidates[] = $envPath;
        }

        $candidates[] = session_save_path();
        $candidates[] = $this->defaultSessionDirectory();
        $candidates[] = sys_get_temp_dir();

        foreach ($candidates as $candidate) {
            $path = $this->normalizeSessionSavePath($candidate);
            if ($path === '') {
                continue;
            }
            if ($this->ensureSessionDirectory($path)) {
                return $path;
            }
        }

        return null;
    }

    private function normalizeSessionSavePath(string $path): string {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        if (str_contains($trimmed, ';')) {
            $parts = array_filter(explode(';', $trimmed));
            $last = end($parts);
            if ($last !== false) {
                $trimmed = (string) $last;
            }
        }

        return $trimmed;
    }

    private function ensureSessionDirectory(string $path): bool {
        if ($path === '') {
            return false;
        }

        if (!is_dir($path)) {
            if (!@mkdir($path, 0775, true) && !is_dir($path)) {
                return false;
            }
        }

        return is_writable($path);
    }

    private function defaultSessionDirectory(): string {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sessions';
    }

    private function resolveActiveNamespace(Request $request): ?string {
        $activeNamespace = $_SESSION['user']['active_namespace'] ?? null;
        if (is_string($activeNamespace) && $activeNamespace !== '') {
            return $activeNamespace;
        }

        if (!isset($_SESSION['user']['id'])) {
            return null;
        }

        try {
            $pdo = $this->resolvePdo($request);

            $userService = new UserService($pdo);
            $record = $userService->getById((int) $_SESSION['user']['id']);
            if ($record === null) {
                return null;
            }

            $sessionService = new SessionService($pdo);
            $activeNamespace = $sessionService->resolveActiveNamespace($record['namespaces']);

            $_SESSION['user']['namespaces'] = $record['namespaces'];
            $_SESSION['user']['active_namespace'] = $activeNamespace;

            return $activeNamespace;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveRequestedNamespace(Request $request): ?string {
        $validator = new NamespaceValidator();
        return $validator->normalizeCandidate($request->getQueryParams()['namespace'] ?? null);
    }

    private function applyRequestedNamespace(Request $request, string $requestedNamespace): void {
        if (!isset($_SESSION['user']['id'])) {
            return;
        }

        $record = $this->loadUserRecord($request);
        if ($record === null) {
            return;
        }

        $_SESSION['user']['namespaces'] = $record['namespaces'];

        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowed = $accessService->resolveAllowedNamespaces($role);

        if (!$accessService->shouldExposeNamespace($requestedNamespace, $allowed, $role)) {
            return;
        }

        if ($role === Roles::ADMIN) {
            $_SESSION['user']['active_namespace'] = $requestedNamespace;
            return;
        }

        $pdo = $this->resolvePdo($request);
        $sessionService = new SessionService($pdo);
        $_SESSION['user']['active_namespace'] = $sessionService->resolveActiveNamespace(
            $record['namespaces'],
            $requestedNamespace
        );
    }

    /**
     * @return array{
     *     id:int,
     *     username:string,
     *     password:string,
     *     email:?string,
     *     role:string,
     *     active:bool,
     *     namespaces:list<array{namespace:string,is_default:bool}>
     * }|null
     */
    private function loadUserRecord(Request $request): ?array {
        try {
            $pdo = $this->resolvePdo($request);
            $userService = new UserService($pdo);
            return $userService->getById((int) $_SESSION['user']['id']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolvePdo(Request $request): PDO {
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        return $pdo;
    }
}
