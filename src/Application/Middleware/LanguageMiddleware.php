<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Service\TranslationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $first = empty($_SESSION['lang']);
        $params = $request->getQueryParams();
        $locale = (string)($params['lang'] ?? ($_SESSION['lang'] ?? $this->defaultLocale));
        $_SESSION['lang'] = $locale;
        $this->translator->loadLocale($locale);
        $request = $request
            ->withAttribute('lang', $this->translator->getLocale())
            ->withAttribute('translator', $this->translator);
        if ($first && empty($_SESSION['user']) && str_starts_with($request->getUri()->getPath(), '/admin/events')) {
            return (new SlimResponse())
                ->withHeader('Location', '/admin/events')
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
