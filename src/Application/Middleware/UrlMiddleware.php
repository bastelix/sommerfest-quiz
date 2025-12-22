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
}
