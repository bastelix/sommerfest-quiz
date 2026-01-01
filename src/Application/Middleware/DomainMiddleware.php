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
use Throwable;

/**
 * Determines domain type based on request host.
 */
class DomainMiddleware implements MiddlewareInterface
{
    private MarketingDomainProvider $domainProvider;

    public function __construct(MarketingDomainProvider $domainProvider)
    {
        $this->domainProvider = $domainProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Request $request, RequestHandler $handler): Response {
        $originalHost = strtolower($request->getUri()->getHost());
        $host = $this->normalizeHost($originalHost);
        $marketingHost = $this->normalizeHost($originalHost, stripAdmin: false);

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

        if (
            $domainType !== 'marketing'
            && (
                $effectiveMainDomain === ''
                || $domainType === null
            )
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

        $domainNamespace = null;
        try {
            $pdo = Database::connectFromEnv();
            $service = new DomainService($pdo);
            $domain = $service->getDomainForHost($originalHost, includeInactive: true);
            if ($domain !== null && $domain['namespace'] !== null) {
                $domainNamespace = $domain['namespace'];
            }
        } catch (Throwable $e) {
            // Ignore errors so the request can continue even if the table is missing.
        }

        $request = $request
            ->withAttribute('domainType', $domainType);

        if ($domainNamespace !== null) {
            $request = $request->withAttribute('domainNamespace', $domainNamespace);
        }

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
