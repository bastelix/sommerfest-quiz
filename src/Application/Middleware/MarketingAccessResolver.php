<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Database;
use App\Service\MarketingDomainProvider;
use App\Support\DomainNameHelper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Determine whether the current request targets a main or marketing domain.
 *
 * This is intentionally NOT a full PSR-15 middleware but a stateless helper
 * that can be called from route handlers. It returns the enriched request
 * together with a boolean flag indicating marketing access.
 *
 * @return array{0: ServerRequestInterface, 1: bool}
 */
final class MarketingAccessResolver
{
    /**
     * @return array{0: ServerRequestInterface, 1: bool}
     */
    public static function resolve(ServerRequestInterface $request): array
    {
        $domainType = $request->getAttribute('domainType');
        if (!in_array($domainType, ['main', 'marketing'], true)) {
            $host = strtolower($request->getUri()->getHost());
            $normalizedHost = DomainNameHelper::normalize($host, stripAdmin: false);
            $marketingDomainProvider = DomainNameHelper::getMarketingDomainProvider();
            if ($marketingDomainProvider === null) {
                $marketingDomainProvider = new MarketingDomainProvider(
                    static function (): \PDO {
                        return Database::connectFromEnv();
                    }
                );
                DomainNameHelper::setMarketingDomainProvider($marketingDomainProvider);
            }
            $mainDomain = strtolower((string) $marketingDomainProvider->getMainDomain());
            $normalizedMainDomain = DomainNameHelper::normalize($mainDomain, stripAdmin: false);

            $computed = 'tenant';
            if ($normalizedMainDomain === '' || $normalizedHost === $normalizedMainDomain) {
                $computed = 'main';
            } else {
                $marketingDomains = $marketingDomainProvider->getMarketingDomains(stripAdmin: false);
                $marketingList = array_filter(array_map(
                    static fn (string $domain): string => DomainNameHelper::normalize($domain, stripAdmin: false),
                    $marketingDomains
                ));
                if ($marketingList !== [] && in_array($normalizedHost, $marketingList, true)) {
                    $computed = 'marketing';
                }
            }

            $request = $request->withAttribute('domainType', $computed);
            $domainType = $computed;
        }

        return [$request, in_array($domainType, ['main', 'marketing'], true)];
    }
}
