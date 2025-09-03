<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\UrlService;
use App\Infrastructure\Database;
use PDO;

/**
 * Displays the catalog administration overview page.
 */
class AdminCatalogController
{
    private CatalogService $service;

    /**
     * Inject dependencies.
     */
    public function __construct(CatalogService $service)
    {
        $this->service = $service;
    }

    /**
     * Render the catalog administration page.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $cfgSvc = new ConfigService($pdo);
        $eventSvc = new EventService($pdo);

        $params = $request->getQueryParams();
        $uid = (string)($params['event'] ?? '');
        $qrOptions = $params;
        unset($qrOptions['event']);
        if ($uid !== '') {
            $cfgSvc->getConfigForEvent($uid);
            $event = $eventSvc->getByUid($uid) ?? $eventSvc->getFirst();
        } else {
            $cfg = $cfgSvc->getConfig();
            $event = null;
            $evUid = (string)($cfg['event_uid'] ?? '');
            if ($evUid !== '') {
                $event = $eventSvc->getByUid($evUid);
            }
            if ($event === null) {
                $event = $eventSvc->getFirst();
            }
        }
        $catalogsJson = $this->service->read('catalogs.json');
        $catalogs = [];
        if ($catalogsJson !== null) {
            $catalogs = json_decode($catalogsJson, true) ?? [];
        }
        $baseUrl = UrlService::determineBaseUrl($request);

        return $view->render($response, 'kataloge.twig', [
            'kataloge' => $catalogs,
            'baseUrl' => $baseUrl,
            'event' => $event,
            'qrOptions' => $qrOptions,
        ]);
    }

    /**
     * Provide paginated catalog data as JSON.
     */
    public function catalogs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, (int) ($params['perPage'] ?? 50));
        $order = (string) ($params['order'] ?? 'asc');
        $offset = ($page - 1) * $perPage;
        $items = $this->service->fetchPagedCatalogs($offset, $perPage, $order);
        $total = $this->service->countCatalogs();

        $payload = [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];

        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
