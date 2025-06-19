<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResultService;
use App\Service\ConfigService;
use App\Service\CatalogService;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ResultController
{
    private ResultService $service;
    private ConfigService $config;
    private string $photoDir;

    public function __construct(ResultService $service, ConfigService $config, string $photoDir)
    {
        $this->service = $service;
        $this->config = $config;
        $this->photoDir = rtrim($photoDir, '/');
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
        $rows[] = ['Name', 'Versuch', 'Katalog', 'Richtige', 'Gesamt', 'Zeit', 'Rätselwort', 'Beweisfoto'];
        foreach ($data as $r) {
            $rows[] = [
                (string)($r['name'] ?? ''),
                (int)($r['attempt'] ?? 0),
                (string)($r['catalog'] ?? ''),
                (int)($r['correct'] ?? 0),
                (int)($r['total'] ?? 0),
                date('Y-m-d H:i', (int)($r['time'] ?? 0)),
                isset($r['puzzleTime']) ? date('Y-m-d H:i', (int)$r['puzzleTime']) : '',
                (string)($r['photo'] ?? '')
            ];
        }
        // prepend UTF-8 BOM for better compatibility with spreadsheet tools
        $content = "\xEF\xBB\xBF" . $this->buildCsv($rows);
        $response->getBody()->write($content);

        $cfg = $this->config->getConfig();
        $name = ($cfg['header'] ?? 'results') . '.csv';

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
    }

    public function post(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (is_array($data)) {
            if (isset($data['puzzleTime'])) {
                $name = (string)($data['name'] ?? '');
                $catalog = (string)($data['catalog'] ?? '');
                $time = (int)$data['puzzleTime'];
                $this->service->markPuzzle($name, $catalog, $time);
            } else {
                $this->service->add($data);
            }
        }
        return $response->withStatus(204);
    }

    public function page(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $results = $this->service->getAll();

        $pdo = Database::connectFromEnv();
        $catalogSvc = new CatalogService($pdo);
        $json = $catalogSvc->read('catalogs.json');
        $map = [];
        if ($json !== null) {
            $list = json_decode($json, true) ?: [];
            foreach ($list as $c) {
                $name = $c['name'] ?? ($c['id'] ?? '');
                if (isset($c['uid'])) {
                    $map[$c['uid']] = $name;
                }
                if (isset($c['id'])) {
                    $map[$c['id']] = $name;
                }
            }
        }
        foreach ($results as &$row) {
            $cat = $row['catalog'] ?? '';
            if (isset($map[$cat])) {
                $row['catalog'] = $map[$cat];
            }
        }
        unset($row);

        return $view->render($response, 'results.twig', ['results' => $results]);
    }

    public function delete(Request $request, Response $response): Response
    {
        $this->service->clear();
        if (is_dir($this->photoDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->photoDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
        }
        return $response->withStatus(204);
    }

    /**
     * @param list<array<string|int>> $rows
     */
    private function buildCsv(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $cells = array_map(static function ($v) {
                return '"' . str_replace('"', '""', (string) $v) . '"';
            }, $row);
            $lines[] = implode(';', $cells);
        }
        return implode("\n", $lines) . "\n";
    }
}
