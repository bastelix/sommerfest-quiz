<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Domain\Roles;
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
     * Check if the current session has one of the given roles.
     */
    private function hasRole(string ...$roles): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $role = $_SESSION['user']['role'] ?? null;
        return $role !== null && in_array($role, $roles, true);
    }

    /**
     * Retrieve a catalog JSON file or redirect to its public view.
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $file = basename($args['file']);
        $accept = strtolower($request->getHeaderLine('Accept'));
        $params = $request->getQueryParams();
        $event = (string) ($params['event'] ?? '');

        if ($accept === '' || strpos($accept, 'application/json') === false) {
            $slug = $this->service->slugByFile($file) ?? pathinfo($file, PATHINFO_FILENAME);
            $location = '/?';
            if ($event !== '') {
                $location .= 'event=' . urlencode($event) . '&';
            }
            $location .= 'katalog=' . urlencode($slug);

            return $response
                ->withHeader('Location', $location)
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
        if (!$this->hasRole(Roles::ADMIN, Roles::CATALOG_EDITOR)) {
            return $response->withStatus(403);
        }
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

        if ($this->service->slugByFile($file) === null) {
            $this->service->createCatalog($file);
        }
        $this->service->write($file, $data);

        return $response->withStatus(204);
    }

    /**
     * Create a new catalog or overwrite the existing one.
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        if (!$this->hasRole(Roles::ADMIN, Roles::CATALOG_EDITOR)) {
            return $response->withStatus(403);
        }
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
        if (!$this->hasRole(Roles::ADMIN, Roles::CATALOG_EDITOR)) {
            return $response->withStatus(403);
        }
        $file = basename($args['file']);
        $this->service->delete($file);

        return $response->withStatus(204);
    }

    /**
     * Remove a question from the specified catalog.
     */
    public function deleteQuestion(Request $request, Response $response, array $args): Response
    {
        if (!$this->hasRole(Roles::ADMIN, Roles::CATALOG_EDITOR)) {
            return $response->withStatus(403);
        }
        $file = basename($args['file']);
        $index = (int) $args['index'];
        $this->service->deleteQuestion($file, $index);

        return $response->withStatus(204);
    }
}
