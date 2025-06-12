<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CatalogController
{
    private CatalogService $service;

    public function __construct(CatalogService $service)
    {
        $this->service = $service;
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $accept = strtolower($request->getHeaderLine('Accept'));
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $isJson = $ext === 'json' || strpos($accept, 'application/json') !== false;
        // redirect only when neither the file extension nor the Accept header
        // indicate a JSON response
        if (!$isJson) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            return $response
                ->withHeader('Location', '/?katalog=' . urlencode($id))
                ->withStatus(302);
        }

        $content = $this->service->read($file);
        if ($content === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function post(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $data = $request->getParsedBody();

        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
            if (!is_array($data)) {
                return $response->withStatus(400);
            }
        } elseif (!is_array($data)) {
            return $response->withStatus(400);
        }

        $this->service->write($file, $data);

        return $response->withStatus(204);
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        $this->service->write($file, $data ?? []);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $this->service->delete($file);

        return $response->withStatus(204);
    }

    public function deleteQuestion(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $index = (int) $args['index'];
        $this->service->deleteQuestion($file, $index);

        return $response->withStatus(204);
    }
}
