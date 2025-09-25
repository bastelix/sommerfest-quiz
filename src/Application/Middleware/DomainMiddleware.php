<?php

declare(strict_types=1);

namespace App\Application\Middleware;

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
    /**
     * {@inheritDoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $originalHost = strtolower($request->getUri()->getHost());
        $host = $this->normalizeHost($originalHost);
        $marketingHost = $this->normalizeHost($originalHost, stripAdmin: false);

        $mainDomain = $this->normalizeHost((string) getenv('MAIN_DOMAIN'));
        $marketingDomains = $this->getMarketingDomains();

        $domainType = null;

        if ($mainDomain !== '') {
            if ($host === $mainDomain) {
                $domainType = 'main';
            } elseif ($host !== '' && str_ends_with($host, '.' . $mainDomain)) {
                $domainType = 'tenant';
            }
        }

        if ($domainType === null && in_array($marketingHost, $marketingDomains, true)) {
            $domainType = 'marketing';
        }

        if (
            $mainDomain === ''
            || $domainType === null
        ) {
            $message = 'Invalid main domain configuration.';
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
                $response->getBody()->write(json_encode(['error' => $message]));

                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write($message);

            return $response->withHeader('Content-Type', 'text/html');
        }

        $request = $request->withAttribute('domainType', $domainType);

        return $handler->handle($request);
    }

    /**
     * @return list<string>
     */
    private function getMarketingDomains(): array
    {
        $env = (string) getenv('MARKETING_DOMAINS');
        if ($env === '') {
            return [];
        }

        $domains = preg_split('/[\s,]+/', $env);
        if ($domains === false) {
            return [];
        }

        $normalized = [];
        foreach ($domains as $domain) {
            $domain = trim($domain);
            if ($domain === '') {
                continue;
            }

            $normalized[] = $this->normalizeHost($domain, stripAdmin: false);
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeHost(string $host, bool $stripAdmin = true): string
    {
        $host = strtolower($host);

        $pattern = $stripAdmin ? '/^(www|admin)\./' : '/^www\./';

        return (string) preg_replace($pattern, '', $host);
    }
}
