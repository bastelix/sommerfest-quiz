<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * CRUD operations for question catalogs.
 */
class CatalogController
{
    private CatalogService $service;

    /**
     * Inject catalog service dependency.
     */
    public function __construct(CatalogService $service)
    {
        $this->service = $service;
    }

    /**
     * Retrieve a catalog JSON file or redirect to its public view.
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $accept = strtolower($request->getHeaderLine('Accept'));

        if ($accept === '' || strpos($accept, 'application/json') === false) {
            $slug = $this->service->slugByFile($file) ?? pathinfo($file, PATHINFO_FILENAME);
            return $response
                ->withHeader('Location', '/?katalog=' . urlencode($slug))
                ->withStatus(302);
        }

        $content = $this->service->read($file);
        if ($content === null) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update the specified catalog file with the provided data.
     */
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

    /**
     * Create a new catalog or overwrite the existing one.
     */
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

    /**
     * Delete a catalog file and all its questions.
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $this->service->delete($file);

        return $response->withStatus(204);
    }

    /**
     * Remove a question from the specified catalog.
     */
    public function deleteQuestion(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $index = (int) $args['index'];
        $this->service->deleteQuestion($file, $index);

        return $response->withStatus(204);
    }
}
