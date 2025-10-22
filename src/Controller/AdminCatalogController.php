<?php

declare(strict_types=1);

namespace App\Controller;

use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\CatalogService;

/**
 * Provides catalog data for the administration dashboard.
 */
class AdminCatalogController
{
    private CatalogService $service;

    /**
     * Inject dependencies.
     */
    public function __construct(CatalogService $service) {
        $this->service = $service;
    }

    /**
     * Provide paginated catalog data as JSON.
     */
    public function catalogs(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, (int) ($params['perPage'] ?? 50));
        $order = (string) ($params['order'] ?? 'asc');
        $offset = ($page - 1) * $perPage;
        try {
            $items = $this->service->fetchPagedCatalogs($offset, $perPage, $order);
            $total = $this->service->countCatalogs();

            $payload = [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
            ];
        } catch (PDOException $exception) {
            $payload = [
                'useLegacy' => true,
            ];
        }

        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Return the first catalog (name and description only).
     */
    public function sample(Request $request, Response $response): Response {
        $items = $this->service->fetchPagedCatalogs(0, 1, 'asc');
        $first = $items[0] ?? null;
        $payload = $first === null
            ? null
            : [
                'name' => $first['name'] ?? '',
                'description' => $first['description'] ?? '',
            ];
        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
