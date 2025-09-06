<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Service\TranslationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;

class LanguageMiddleware implements MiddlewareInterface
{
    private TranslationService $translator;
    private string $defaultLocale;

    public function __construct(TranslationService $translator, string $defaultLocale = 'de')
    {
        $this->translator = $translator;
        $this->defaultLocale = $defaultLocale;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $first = empty($_SESSION['lang']);
        $params = $request->getQueryParams();
        $locale = (string)($params['lang'] ?? ($_SESSION['lang'] ?? $this->defaultLocale));
        $_SESSION['lang'] = $locale;
        $this->translator->loadLocale($locale);
        $request = $request
            ->withAttribute('lang', $this->translator->getLocale())
            ->withAttribute('translator', $this->translator);
        $path = $request->getUri()->getPath();
        $base = RouteContext::fromRequest($request)->getBasePath();
        if (
            $first &&
            empty($_SESSION['user']) &&
            str_starts_with($path, $base . '/admin/dashboard')
        ) {
            return (new SlimResponse())
                ->withHeader('Location', $base . '/admin/dashboard')
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
