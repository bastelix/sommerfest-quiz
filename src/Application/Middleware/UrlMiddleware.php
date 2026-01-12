<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Service\NamespaceResolver;
use App\Service\ProjectSettingsService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Views\Twig;

/**
 * Adds baseUrl and canonicalUrl variables to Twig globals based on the current request.
 */
class UrlMiddleware implements MiddlewareInterface
{
    private Twig $twig;
    private ProjectSettingsService $projectSettings;

    public function __construct(Twig $twig, ?ProjectSettingsService $projectSettings = null) {
        $this->twig = $twig;
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService();
    }

    public function process(Request $request, RequestHandler $handler): Response {
        $uri = $request->getUri();
        $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : 'http';
        $host = $uri->getHost() !== '' ? $uri->getHost() : 'localhost';
        $port = $uri->getPort();

        $origin = $scheme . '://' . $host;
        if ($port !== null && $port !== 80 && $port !== 443) {
            $origin .= ':' . $port;
        }

        $basePath = $this->twig->getEnvironment()->getGlobals()['basePath'] ?? '';
        $baseUrl = $origin . $basePath;

        $path = $uri->getPath();
        if ($path === '') {
            $path = '/';
        }
        $canonicalUrl = $origin . $path;

        $env = $this->twig->getEnvironment();
        $env->addGlobal('baseUrl', $baseUrl);
        $env->addGlobal('canonicalUrl', $canonicalUrl);
        $env->addGlobal('namespaceTokensVersion', $this->getNamespaceTokensVersion());
        $env->addGlobal('marketingSchemes', $this->getMarketingSchemes());

        $privacyUrl = rtrim($basePath, '/') . '/datenschutz';
        try {
            $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
            $locale = (string) ($request->getAttribute('lang') ?? ($_SESSION['lang'] ?? 'de'));
            $cookieSettings = $this->projectSettings->getCookieConsentSettings($namespace);
            $privacyUrl = $this->projectSettings->resolvePrivacyUrlForSettings($cookieSettings, $locale, $basePath);
        } catch (\Throwable $error) {
            $privacyUrl = rtrim($basePath, '/') . '/datenschutz';
        }
        $env->addGlobal('privacyUrl', $privacyUrl);

        return $handler->handle($request);
    }

    private function getNamespaceTokensVersion(): string
    {
        $path = dirname(__DIR__, 3) . '/public/css/namespace-tokens.css';
        clearstatcache(false, $path);
        if (!file_exists($path)) {
            return (string) time();
        }

        $timestamp = filemtime($path);

        return $timestamp === false ? (string) time() : (string) $timestamp;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getMarketingSchemes(): array
    {
        $path = dirname(__DIR__, 3) . '/config/marketing-design-tokens.php';
        if (!is_file($path)) {
            return [];
        }

        $schemes = require $path;

        return is_array($schemes) ? $schemes : [];
    }
}
