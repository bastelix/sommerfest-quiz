<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Dynamically generates robots.txt per domain/namespace.
 *
 * Replaces the static public/robots.txt to allow AI crawlers
 * (GPTBot, Claude-Web, PerplexityBot, etc.) while keeping
 * admin and private areas blocked.
 */
final class RobotsTxtController
{
    /** Paths that must never be crawled. */
    private const BLOCKED_PATHS = [
        '/admin',
        '/api/',
        '/login',
        '/logout',
        '/password',
        '/onboarding',
        '/healthz',
    ];

    /** Public paths for the general wildcard user-agent. */
    private const ALLOWED_PATHS = [
        '/$',
        '/landing',
        '/calserver',
        '/faq',
        '/datenschutz',
        '/impressum',
        '/lizenz',
        '/wiki/',
        '/news/',
        '/llms.txt',
        '/llms-full.txt',
        '/sitemap.xml',
        '/feed.xml',
        '/feed.atom',
    ];

    /** AI crawlers that should get broad access to public content. */
    private const AI_CRAWLERS = [
        'GPTBot',
        'ChatGPT-User',
        'Claude-Web',
        'Google-Extended',
        'PerplexityBot',
        'Applebot-Extended',
    ];

    /** Crawlers to block entirely. */
    private const BLOCKED_CRAWLERS = [
        'Bytespider',
        'CCBot',
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        if ($uri->getPort() !== null && $uri->getPort() !== 443 && $uri->getPort() !== 80) {
            $baseUrl .= ':' . $uri->getPort();
        }

        $lines = [];

        // AI crawlers – broad access to public content
        foreach (self::AI_CRAWLERS as $crawler) {
            $lines[] = 'User-agent: ' . $crawler;
            foreach (self::BLOCKED_PATHS as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
            $lines[] = '';
        }

        // Blocked crawlers
        foreach (self::BLOCKED_CRAWLERS as $crawler) {
            $lines[] = 'User-agent: ' . $crawler;
            $lines[] = 'Disallow: /';
            $lines[] = '';
        }

        // Default – restrictive with explicit allows
        $lines[] = 'User-agent: *';
        foreach (self::ALLOWED_PATHS as $path) {
            $lines[] = 'Allow: ' . $path;
        }
        foreach (self::BLOCKED_PATHS as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
        $lines[] = '';

        // Sitemap reference
        $lines[] = 'Sitemap: ' . $baseUrl . '/sitemap.xml';
        $lines[] = '';

        $content = implode("\n", $lines);
        $response->getBody()->write($content);

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
