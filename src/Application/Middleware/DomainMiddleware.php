<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Database;
use App\Service\DomainService;
use App\Service\MarketingDomainProvider;
use App\Support\DomainNameHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Determines domain type based on request host.
 */
class DomainMiddleware implements MiddlewareInterface
{
    private MarketingDomainProvider $domainProvider;

    public function __construct(
        MarketingDomainProvider $domainProvider
    ) {
        $this->domainProvider = $domainProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Request $request, RequestHandler $handler): Response {
        $originalHost = strtolower($request->getUri()->getHost());
        DomainNameHelper::setMarketingDomainProvider($this->domainProvider);
        $host = $this->normalizeHost($originalHost);
        $marketingHost = $this->normalizeHost($originalHost, stripAdmin: false);
        $path = $this->normalizePath($request);

        if ($this->isLightweightHealthCheck($path)) {
            return $this->createLightweightHealthResponse($request);
        }

        $mainDomainRaw = $this->domainProvider->getMainDomain();
        $mainDomainSource = $this->domainProvider->getMainDomainSource();
        $mainDomain = $mainDomainRaw !== null ? $this->normalizeHost($mainDomainRaw) : '';
        $effectiveMainDomain = $mainDomain;
        $fallbackSource = 'none';

        if ($effectiveMainDomain === '') {
            $domainEnv = getenv('DOMAIN');
            if ($domainEnv !== false) {
                $candidate = $this->normalizeHost((string) $domainEnv);
                if ($candidate !== '') {
                    $effectiveMainDomain = $candidate;
                    $fallbackSource = 'env:DOMAIN';
                }
            }
        }

        if ($effectiveMainDomain === '' && $host !== '') {
            $effectiveMainDomain = $host;
            $fallbackSource = 'request-host';
        }

        $logMessage = sprintf(
            'DomainMiddleware resolved main domain "%s" (source: %s, fallback: %s, request host: "%s")',
            $effectiveMainDomain !== '' ? $effectiveMainDomain : '(empty)',
            $mainDomainSource ?? 'none',
            $fallbackSource,
            $marketingHost !== '' ? $marketingHost : $host
        );

        error_log($logMessage);
        $marketingDomains = $this->getMarketingDomains();

        $domainType = null;
        $allowLocalHost = $this->isLocalHost($host) || $this->isLocalHost($marketingHost);

        if ($effectiveMainDomain !== '') {
            if ($host === $effectiveMainDomain) {
                $domainType = 'main';
            } elseif ($host !== '' && str_ends_with($host, '.' . $effectiveMainDomain)) {
                $domainType = 'tenant';
            }
        }

        if ($domainType === null && in_array($host, $marketingDomains, true)) {
            $domainType = 'marketing';
        }

        if ($domainType === null && $allowLocalHost) {
            $domainType = 'main';
        }

        if ($domainType === null) {
            $domainType = 'marketing';
        }

        if (
            $domainType !== 'marketing'
            && $effectiveMainDomain === ''
            && !$allowLocalHost
        ) {
            $message = 'Main domain is not configured. Please set MAIN_DOMAIN (preferred) or DOMAIN to the canonical host.';
            error_log(sprintf(
                'MAIN_DOMAIN misconfiguration: "%s" (request host: "%s")',
                $mainDomain,
                $marketingHost !== '' ? $marketingHost : $host
            ));

            $accept = $request->getHeaderLine('Accept');
            $path = $request->getUri()->getPath();
            $xhr = $request->getHeaderLine('X-Requested-With');

            $isApi = str_starts_with($path, '/api/')
                || str_contains($accept, 'application/json')
                || $xhr === 'fetch';

            $response = new SlimResponse(403);

            if ($isApi) {
                $response->getBody()->write(json_encode([
                    'error' => $message,
                    'expectedEnv' => ['MAIN_DOMAIN', 'DOMAIN'],
                    'requestHost' => $marketingHost !== '' ? $marketingHost : $host,
                ]));

                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(
                '<p>'
                . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
                . '</p><p>Setzen Sie die Umgebungsvariable <code>MAIN_DOMAIN</code> '
                . 'oder ersatzweise <code>DOMAIN</code>, um die Anfrage-Domain zu validieren.</p>'
            );

            return $response->withHeader('Content-Type', 'text/html');
        }

        $pdo = Database::connectFromEnv();
        $service = new DomainService($pdo);
        $domain = $service->getDomainForHost($originalHost, includeInactive: true);

        if ($domain === null || $domain['namespace'] === null) {
            error_log(sprintf(
                'DomainMiddleware missing domain mapping for host "%s" (normalized: "%s")',
                $originalHost !== '' ? $originalHost : '(empty)',
                $marketingHost !== '' ? $marketingHost : $host
            ));

            return $this->createMissingDomainResponse(
                $request,
                $marketingHost !== '' ? $marketingHost : $host,
                $effectiveMainDomain
            );
        }

        $domainNamespace = $domain['namespace'];

        $request = $request
            ->withAttribute('domainType', $domainType)
            ->withAttribute('domainNamespace', $domainNamespace)
            ->withAttribute('namespace', $domainNamespace)
            ->withAttribute('pageNamespace', $domainNamespace);

        return $handler->handle($request);
    }

    /**
     * @return list<string>
     */
    private function getMarketingDomains(): array {
        $domains = [];

        foreach ($this->domainProvider->getMarketingDomains() as $domain) {
            $normalized = $this->normalizeHost($domain);
            if ($normalized === '') {
                continue;
            }

            $domains[$normalized] = true;
        }

        return array_keys($domains);
    }

    private function normalizeHost(string $host, bool $stripAdmin = true): string {
        return DomainNameHelper::normalize($host, $stripAdmin);
    }

    private function normalizePath(Request $request): string
    {
        $path = $request->getUri()->getPath();

        return $path === '' ? '/' : $path;
    }

    private function isLightweightHealthCheck(string $path): bool
    {
        $normalized = rtrim($path, '/');
        if ($normalized === '') {
            $normalized = '/';
        }

        return in_array($normalized, ['/healthz-lite', '/healthz/ping'], true);
    }

    private function createLightweightHealthResponse(Request $request): Response
    {
        $payload = [
            'status' => 'ok',
            'app' => 'quizrace',
            'time' => gmdate('c'),
        ];

        $response = new SlimResponse(200);

        if (strtoupper($request->getMethod()) !== 'HEAD') {
            $response->getBody()->write(json_encode($payload));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function createMissingDomainResponse(Request $request, string $requestedHost, string $mainDomain): Response
    {
        $isApi = $this->isApiRequest($request);
        $isRedirectCandidate = !$isApi
            && $mainDomain !== ''
            && $requestedHost !== ''
            && $requestedHost !== $mainDomain
            && !str_ends_with($requestedHost, '.' . $mainDomain);

        $status = $isRedirectCandidate ? 302 : 404;
        $response = new SlimResponse($status);
        $message = sprintf('Requested domain "%s" is not registered.', $requestedHost !== '' ? $requestedHost : '(unknown)');

        if ($isRedirectCandidate) {
            $target = $request->getUri()->withHost($mainDomain)->withPort(null);
            $response = $response->withHeader('Location', (string) $target);
        }

        if ($isApi) {
            $response->getBody()->write(json_encode([
                'error' => $message,
                'host' => $requestedHost,
                'mainDomain' => $mainDomain !== '' ? $mainDomain : null,
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(
            '<p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>'
            . ($mainDomain !== ''
                ? '<p>Try the main domain <a href="https://' . htmlspecialchars($mainDomain, ENT_QUOTES, 'UTF-8')
                    . '">' . htmlspecialchars($mainDomain, ENT_QUOTES, 'UTF-8') . '</a>.</p>'
                : '<p>No main domain is configured.</p>')
        );

        return $response->withHeader('Content-Type', 'text/html');
    }

    private function isApiRequest(Request $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        $path = $request->getUri()->getPath();
        $xhr = $request->getHeaderLine('X-Requested-With');

        return str_starts_with($path, '/api/')
            || str_contains($accept, 'application/json')
            || $xhr === 'fetch';
    }

    private function isLocalHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return str_ends_with($host, '.localhost');
    }
}
