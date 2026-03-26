<?php

declare(strict_types=1);

namespace App\Application\ResponseEmitter;

use Psr\Http\Message\ResponseInterface;
use Slim\ResponseEmitter as SlimResponseEmitter;

/**
 * Emits HTTP responses with additional CORS headers.
 */
class ResponseEmitter extends SlimResponseEmitter
{
    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $response = $response
            ->withHeader(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, Accept, Origin, Authorization, Mcp-Session-Id',
            )
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Expose-Headers', 'Mcp-Session-Id')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');

        if ($origin !== '' && self::isAllowedOrigin($origin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Origin', $origin);
        }

        if (ob_get_contents()) {
            ob_clean();
        }

        parent::emit($response);
    }

    private static function isAllowedOrigin(string $origin): bool
    {
        $allowed = getenv('CORS_ALLOWED_ORIGINS');
        if ($allowed !== false && $allowed !== '') {
            $list = array_map('trim', explode(',', $allowed));
            return in_array($origin, $list, true);
        }

        $allowedHosts = [];
        $domain = getenv('DOMAIN') ?: ($_ENV['DOMAIN'] ?? '');
        $mainDomain = getenv('MAIN_DOMAIN') ?: ($_ENV['MAIN_DOMAIN'] ?? '');
        if ($domain !== '') {
            $allowedHosts[] = $domain;
        }
        if ($mainDomain !== '' && $mainDomain !== $domain) {
            $allowedHosts[] = $mainDomain;
        }

        if ($allowedHosts === []) {
            return true;
        }

        $originHost = parse_url($origin, PHP_URL_HOST) ?: '';
        foreach ($allowedHosts as $host) {
            if ($originHost === $host || str_ends_with($originHost, '.' . $host)) {
                return true;
            }
        }

        return false;
    }
}
