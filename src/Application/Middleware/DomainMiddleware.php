<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Database;
use App\Service\DomainStartPageService;
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
        $mainDomain = $mainDomainRaw !== null ? $this->normalizeHost($mainDomainRaw) : '';
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
        $contactEmail = null;
        try {
            $pdo = Database::connectFromEnv();
            $service = new DomainStartPageService($pdo);
            $config = $service->getConfigForHost($originalHost);
            if ($config !== null) {
                $startPageValue = trim($config['start_page']);
                if ($startPageValue !== '') {
                    $startPage = $startPageValue;
                }

                $email = $config['email'] ?? null;
                if (is_string($email)) {
                    $email = trim($email);
                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $contactEmail = $email;
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore errors so the request can continue even if the table is missing.
        }

        $request = $request
            ->withAttribute('domainType', $domainType)
            ->withAttribute('domainStartPage', $startPage)
            ->withAttribute('domainContactEmail', $contactEmail);

        return $handler->handle($request);
    }

    /**
     * @return list<string>
     */
    private function getMarketingDomains(): array {
        $domains = [];

        foreach ($this->domainProvider->getMarketingDomains(stripAdmin: false) as $domain) {
            $normalized = $this->normalizeHost($domain, stripAdmin: false);
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
}
