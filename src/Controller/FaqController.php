<?php

declare(strict_types=1);

namespace SommerfestQuiz\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FaqController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $path = dirname(__DIR__, 2) . '/templates/faq.php';
        ob_start();
        include $path;
        $content = ob_get_clean();
        $response->getBody()->write($content);
        return $response;
    }
}
