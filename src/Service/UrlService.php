<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Http\Message\ServerRequestInterface as Request;

class UrlService
{
    public static function determineBaseUrl(Request $req): string
    {
        $uri = $req->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        $port = $uri->getPort();
        if ($port !== null && !in_array($port, [80, 443], true)) {
            $baseUrl .= ':' . $port;
        }

        $domain = getenv('DOMAIN');
        if ($domain !== false && $domain !== '') {
            $envHost = parse_url($domain, PHP_URL_HOST) ?: $domain;
            if ($envHost !== '' && $envHost !== $uri->getHost()) {
                if (preg_match('#^https?://#', $domain) === 1) {
                    $baseUrl = rtrim($domain, '/');
                } else {
                    $baseUrl = 'https://' . $domain;
                }
            }
        }

        return $baseUrl;
    }
}
