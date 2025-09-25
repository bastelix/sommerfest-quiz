<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Database;
use App\Service\DomainStartPageService;
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

        $startPage = null;
        try {
            $pdo = Database::connectFromEnv();
            $service = new DomainStartPageService($pdo);
            $startPage = $service->getStartPage($host);
            if ($startPage === null && $marketingHost !== $host) {
                $startPage = $service->getStartPage($marketingHost);
            }
        } catch (Throwable $e) {
            // Ignore errors so the request can continue even if the table is missing.
        }

        $request = $request
            ->withAttribute('domainType', $domainType)
            ->withAttribute('domainStartPage', $startPage);

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
