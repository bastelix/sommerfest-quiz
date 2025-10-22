<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Stream;

use function explode;
use function fopen;
use function gmdate;
use function in_array;
use function sprintf;
use function str_starts_with;
use function strtotime;
use function substr;
use function trim;

/**
 * Utility helpers for adding caching headers and handling conditional requests.
 */
final class HttpCacheHelper
{
    /**
     * Apply Cache-Control, ETag, and Last-Modified headers to the response.
     *
     * When the client provides conditional request headers that match the
     * supplied cache metadata a 304 response is emitted with an empty body.
     */
    public static function apply(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $cacheControl,
        string $etag,
        int $lastModified
    ): ResponseInterface {
        $lastModified = max(0, $lastModified);
        $lastModifiedHeader = gmdate('D, d M Y H:i:s', $lastModified) . ' GMT';

        $notModified = self::isNotModified($request, $etag, $lastModified);

        $response = $response
            ->withHeader('Cache-Control', $cacheControl)
            ->withHeader('ETag', $etag)
            ->withHeader('Last-Modified', $lastModifiedHeader);

        if ($notModified) {
            $empty = new Stream(fopen('php://temp', 'rb+'));
            return $response->withStatus(304)->withBody($empty);
        }

        return $response;
    }

    private static function isNotModified(
        ServerRequestInterface $request,
        string $etag,
        int $lastModified
    ): bool {
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch !== '') {
            $clientEtags = self::parseEtags($ifNoneMatch);
            if (in_array('*', $clientEtags, true) || in_array($etag, $clientEtags, true)) {
                return true;
            }
        }

        $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');
        if ($ifModifiedSince === '') {
            return false;
        }

        $timestamp = strtotime($ifModifiedSince);
        return $timestamp !== false && $timestamp >= $lastModified;
    }

    /**
     * Split the If-None-Match header into a normalized list of ETag values.
     *
     * @return array<int, string>
     */
    private static function parseEtags(string $header): array
    {
        $values = [];
        foreach (explode(',', $header) as $part) {
            $normalized = self::normalizeEtag($part);
            if ($normalized === null) {
                continue;
            }
            $values[] = $normalized;
        }

        return $values;
    }

    private static function normalizeEtag(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if ($trimmed === '*') {
            return '*';
        }
        if (str_starts_with($trimmed, 'W/')) {
            $trimmed = substr($trimmed, 2);
        }
        $trimmed = trim($trimmed);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = trim($trimmed, '"');

        return sprintf('"%s"', $trimmed);
    }
}
