<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResultService;
use App\Service\XlsxExportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ResultController
{
    private ResultService $service;
    private XlsxExportService $xlsx;

    public function __construct(ResultService $service, XlsxExportService $xlsx)
    {
        $this->service = $service;
        $this->xlsx = $xlsx;
    }

    public function get(Request $request, Response $response): Response
    {
        $content = json_encode($this->service->getAll(), JSON_PRETTY_PRINT);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function download(Request $request, Response $response): Response
    {
        $data = $this->service->getAll();
        $rows = [];
        $rows[] = ['Name', 'Versuch', 'Katalog', 'Richtige', 'Gesamt'];
        foreach ($data as $r) {
            $rows[] = [
                (string)($r['name'] ?? ''),
                (int)($r['attempt'] ?? 0),
                (string)($r['catalog'] ?? ''),
                (int)($r['correct'] ?? 0),
                (int)($r['total'] ?? 0),
            ];
        }
        $content = $this->xlsx->build($rows);
        $response->getBody()->write($content);
        if ($this->xlsx->isAvailable()) {
            return $response
                ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->withHeader('Content-Disposition', 'attachment; filename="results.xlsx"');
        }

        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="results.csv"');
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
