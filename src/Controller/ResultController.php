<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResultService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ResultController
{
    private ResultService $service;

    public function __construct(ResultService $service)
    {
        $this->service = $service;
    }

    public function get(Request $request, Response $response): Response
    {
        $content = json_encode($this->service->getAll(), JSON_PRETTY_PRINT);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function download(Request $request, Response $response): Response
    {
        $content = json_encode($this->service->getAll(), JSON_PRETTY_PRINT);
        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="results.json"');
    }

    public function post(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (is_array($data)) {
            $this->service->add($data);
        }
        return $response->withStatus(204);
    }

    public function page(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $results = $this->service->getAll();
        return $view->render($response, 'results.twig', ['results' => $results]);
    }

    public function delete(Request $request, Response $response): Response
    {
        $this->service->clear();
        return $response->withStatus(204);
    }
}
