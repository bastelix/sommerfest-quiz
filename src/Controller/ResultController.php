<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResultService;
use App\Service\ConfigService;
use App\Service\CatalogService;
use App\Service\TeamService;
use App\Service\EventService;
use App\Service\AwardService;
use App\Infrastructure\Database;
use FPDF;
use App\Service\Pdf;
use Intervention\Image\ImageManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

/**
 * Provides CRUD endpoints for quiz results and exposes a results page.
 */
class ResultController
{
    private ResultService $service;
    private ConfigService $config;
    private TeamService $teams;
    private CatalogService $catalogs;
    private EventService $events;
    private string $photoDir;

    /**
     * Inject dependencies and define photo directory.
     */
    public function __construct(
        ResultService $service,
        ConfigService $config,
        TeamService $teams,
        CatalogService $catalogs,
        string $photoDir,
        EventService $events
    ) {
        $this->service = $service;
        $this->config = $config;
        $this->teams = $teams;
        $this->catalogs = $catalogs;
        $this->photoDir = rtrim($photoDir, '/');
        $this->events = $events;
    }

    /**
     * Return all stored results as JSON.
     */
    public function get(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $eventUid = (string)($params['event_uid'] ?? '');
        $content = json_encode($this->service->getAll($eventUid), JSON_PRETTY_PRINT);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Return per-question results as JSON.
     */
    public function getQuestions(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $eventUid = (string)($params['event_uid'] ?? '');
        $content = json_encode($this->service->getQuestionResults($eventUid), JSON_PRETTY_PRINT);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Download all results as a CSV file.
     */
    public function download(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $eventUid = (string)($params['event_uid'] ?? '');
        $data = $this->service->getAll($eventUid);
        $rows = array_map([$this, 'mapResultRow'], $data);
        array_unshift($rows, ['Name', 'Versuch', 'Katalog', 'Richtige', 'Gesamt', 'Zeit', 'Rätselwort', 'Beweisfoto']);
        // prepend UTF-8 BOM for better compatibility with spreadsheet tools
        $content = "\xEF\xBB\xBF" . $this->buildCsv($rows);
        $response->getBody()->write($content);

        $cfg = $this->config->getConfig();
        $name = ($cfg['header'] ?? 'results') . '.csv';

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
    }

    /**
     * Store a new result or mark a puzzle as solved.
     */
    public function post(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $eventUid = (string)($params['event_uid'] ?? '');
        $data = json_decode((string) $request->getBody(), true);
        $result = ['success' => false];
        if (is_array($data)) {
            if ($eventUid === '') {
                $eventUid = (string)($data['event_uid'] ?? '');
            }
            $name = (string)($data['name'] ?? '');
            if (isset($data['puzzleTime'])) {
                $catalog = (string)($data['catalog'] ?? '');
                $time = (int)$data['puzzleTime'];
                $answer = (string)($data['puzzleAnswer'] ?? '');
                $cfg = $this->config->getConfig();
                $expected = (string)($cfg['puzzleWord'] ?? '');
                $feedback = (string)($cfg['puzzleFeedback'] ?? '');
                $a = mb_strtolower(trim($answer), 'UTF-8');
                $e = mb_strtolower(trim($expected), 'UTF-8');
                $result['answer'] = $answer;
                $result['expected'] = $expected;
                $result['normalizedAnswer'] = $a;
                $result['normalizedExpected'] = $e;
                if ($a !== '' && $a === $e) {
                    $result['success'] = $this->service->markPuzzle($name, $catalog, $time, $eventUid);
                    if (!$result['success']) {
                        $this->service->add([
                            'name' => $name,
                            'catalog' => $catalog,
                            'correct' => 0,
                            'total' => 0,
                            'wrong' => [],
                            'puzzleTime' => $time,
                        ], $eventUid);
                        $result['success'] = true;
                    }
                    $result['feedback'] = $feedback;
                }
            } else {
                $this->service->add($data, $eventUid);
                $result['success'] = true;
            }
            if ($name !== '' && $result['success']) {
                $this->teams->addIfMissing($name);
            }
        }
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Render the HTML results page.
     */
    public function page(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $eventUid = (string)($params['event_uid'] ?? '');
        $view = Twig::fromRequest($request);
        $results = $this->service->getAll($eventUid);

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $configSvc = new ConfigService($pdo);
        $catalogSvc = new CatalogService($pdo, $configSvc);
        $json = $catalogSvc->read('catalogs.json');
        $map = [];
        if ($json !== null) {
            $list = json_decode($json, true) ?: [];
            foreach ($list as $c) {
                $name = $c['name'] ?? '';
                if (isset($c['uid'])) {
                    $map[$c['uid']] = $name;
                }
                if (isset($c['sort_order'])) {
                    $map[$c['sort_order']] = $name;
                }
                if (isset($c['slug'])) {
                    $map[$c['slug']] = $name;
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

    /**
     * Clear all results and associated photos.
     */
    public function delete(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $eventUid = (string)($params['event_uid'] ?? '');
        $this->service->clear($eventUid);
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
     * Render a PDF summary for all teams.
     */
    public function pdf(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $eventUid = (string)($params['event_uid'] ?? '');
        $results = $this->service->getAll($eventUid);
        $questionResults = $this->service->getQuestionResults($eventUid);
        $allResults = $results;
        $teams = $this->teams->getAll();

        if ($teams === []) {
            $names = array_merge(
                array_column($results, 'name'),
                array_column($questionResults, 'name')
            );
            $names = array_filter(
                $names,
                static fn ($n) => is_string($n) && $n !== ''
            );
            $teams = array_values(array_unique($names));
        }

        $params = $request->getQueryParams();
        $teamFilter = (string) ($params['team'] ?? $request->getAttribute('team') ?? '');
        if ($teamFilter !== '') {
            $results = array_values(array_filter(
                $results,
                static fn ($r) => ($r['name'] ?? '') === $teamFilter
            ));
            $questionResults = array_values(array_filter(
                $questionResults,
                static fn ($r) => ($r['name'] ?? '') === $teamFilter
            ));
            $teams = array_values(array_filter(
                $teams,
                static fn ($t) => $t === $teamFilter
            ));
        }

        if ($teams === []) {
            $response->getBody()->write('Keine Ergebnisse vorhanden.');
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        }

        $catalogMax = [];
        $scores = [];
        $photos = [];
        $teamTotals = [];
        foreach ($results as $row) {
            $team = (string)($row['name'] ?? '');
            $cat = (string)($row['catalog'] ?? '');
            $correct = (int)($row['correct'] ?? 0);
            $total = (int)($row['total'] ?? 0);
            if (!isset($catalogMax[$cat]) || $total > $catalogMax[$cat]) {
                $catalogMax[$cat] = $total;
            }
            if (!isset($scores[$team][$cat]) || $correct > $scores[$team][$cat]) {
                $scores[$team][$cat] = $correct;
            }
            if (!isset($teamTotals[$team][$cat]) || $total > $teamTotals[$team][$cat]) {
                $teamTotals[$team][$cat] = $total;
            }
            if (!empty($row['photo'])) {
                $photos[$team][] = (string)$row['photo'];
            }
        }
        foreach ($questionResults as $qr) {
            $team = (string)($qr['name'] ?? '');
            if (!empty($qr['photo'])) {
                $photos[$team][] = (string)$qr['photo'];
            }
        }
        $maxPoints = array_sum($catalogMax);

        $catalogCount = 0;
        $catsJson = $this->catalogs->read('catalogs.json');
        if ($catsJson !== null) {
            $list = json_decode($catsJson, true) ?: [];
            $catalogCount = count($list);
        }

        $awardService = new AwardService();
        $rankings = $awardService->computeRankings($allResults, $catalogCount);

        $params = $request->getQueryParams();
        $uid = (string)($params['event'] ?? '');
        if ($uid === '') {
            $event = $this->events->getFirst();
            if ($event === null) {
                return $response->withHeader('Location', '/events')->withStatus(302);
            }
            $uid = (string)$event['uid'];
        } else {
            $event = $this->events->getByUid($uid);
            if ($event === null) {
                $event = $this->events->getFirst();
                if ($event === null) {
                    return $response->withHeader('Location', '/events')->withStatus(302);
                }
                $uid = (string)$event['uid'];
            }
        }
        $cfg = $this->config->getConfigForEvent($uid);
        $title = (string)$event['name'];
        $subtitle = (string)($event['description'] ?? '');
        $logoPath = __DIR__ . '/../../data/' . ltrim((string)($cfg['logoPath'] ?? ''), '/');

        $pdf = new Pdf($title, $subtitle, $logoPath);

        foreach ($teams as $team) {
            $cats = $scores[$team] ?? [];
            $points = array_sum($cats);
            $answered = array_sum($teamTotals[$team] ?? []);

            $pdf->AddPage();

            $imgWidth = 160;
            $imgX = ($pdf->GetPageWidth() - $imgWidth) / 2;

            $pdf->SetXY(10, $pdf->getBodyStartY() + 10);
            $pdf->SetFont('Arial', 'B', 24);
            $pdf->Cell($pdf->GetPageWidth() - 20, 10, $this->sanitizePdfText($team), 0, 2, 'C');
            $pdf->SetFont('Arial', '', 14);
            $denom = $answered > 0 ? $answered : $maxPoints;
            $text = sprintf('Punkte: %d von %d', $points, $denom);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell($pdf->GetPageWidth() - 20, 8, $text, 0, 2, 'C');
            $pdf->SetTextColor(0, 0, 0);

            $awards = $awardService->getAwards($team, $rankings);
            if ($awards !== []) {
                $pdf->Ln(8);
                $pdf->SetFont('Arial', 'B', 18);
                $pdf->Cell(
                    $pdf->GetPageWidth() - 20,
                    9,
                    $this->sanitizePdfText('HERZLICHEN GLÜCKWUNSCH!'),
                    0,
                    2,
                    'C'
                );
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->Cell($pdf->GetPageWidth() - 20, 7, 'AUSZEICHNUNGEN:', 0, 2, 'C');
                $pdf->Ln(2);
                foreach ($awards as $a) {
                    switch ((int) $a['place']) {
                        case 1:
                            $pdf->SetTextColor(212, 175, 55);
                            break;
                        case 2:
                            $pdf->SetTextColor(192, 192, 192);
                            break;
                        case 3:
                            $pdf->SetTextColor(205, 127, 50);
                            break;
                        default:
                            $pdf->SetTextColor(0, 0, 0);
                    }
                    $title = sprintf('%d. %s', (int) $a['place'], $a['title']);
                    $pdf->SetFont('Arial', 'B', 12);
                    $pdf->Cell($pdf->GetPageWidth() - 20, 6, $this->sanitizePdfText($title), 0, 2, 'C');
                    $pdf->SetFont('Arial', 'I', 10);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->MultiCell($pdf->GetPageWidth() - 20, 5, $this->sanitizePdfText($a['desc']), 0, 'C');
                    $pdf->Ln(1);
                }
            }

            if (!empty($photos[$team])) {
                $rel = (string) $photos[$team][0];
                if (str_starts_with($rel, '/photo/')) {
                    $rel = substr($rel, 7);
                }
                $file = $this->photoDir . '/' . ltrim($rel, '/');
                if (is_readable($file)) {
                    $tmp = null;
                    if (str_ends_with(strtolower($file), '.webp')) {
                        $manager = extension_loaded('imagick') ? ImageManager::imagick() : ImageManager::gd();
                        $img = $manager->read($file);
                        $tmp = tempnam(sys_get_temp_dir(), 'photo') . '.png';
                        $img->save($tmp, 80);
                        $file = $tmp;
                    }
                    $imgY = $pdf->GetY() + 25;
                    $imgX = ($pdf->GetPageWidth() - $imgWidth) / 2;

                    $imgSize = @getimagesize($file);
                    $imgHeight = 100.0;
                    if ($imgSize !== false && $imgSize[0] > 0) {
                        $imgHeight = $imgWidth * ($imgSize[1] / $imgSize[0]);
                    }
                    $marginBottom = 5.0;
                    $footerY = $pdf->GetPageHeight() - 10;
                    $availableHeight = $footerY - $imgY - $marginBottom;
                    if ($imgHeight > $availableHeight) {
                        $scale = $availableHeight / $imgHeight;
                        $imgHeight = $availableHeight;
                        $imgWidth = $imgWidth * $scale;
                        $imgX = ($pdf->GetPageWidth() - $imgWidth) / 2;
                    }
                    $pdf->Image($file, $imgX, $imgY, $imgWidth, $imgHeight);
                    $pdf->SetY($imgY + $imgHeight + $marginBottom);
                    if ($tmp !== null) {
                        unlink($tmp);
                    }
                }
            }

            $footerY = $pdf->GetPageHeight() - 10;
            $pdf->SetLineWidth(0.2);
            $pdf->Line(10, $footerY, $pdf->GetPageWidth() - 10, $footerY);
        }

        $output = $pdf->Output('S');
        $response->getBody()->write($output);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="results.pdf"');
    }

    /**
     * Normalize a single result entry for CSV export.
     *
     * @param array<string,mixed> $r
     * @return list<string|int>
     */
    private function mapResultRow(array $r): array {
        return [
            (string)($r['name'] ?? ''),
            (int)($r['attempt'] ?? 0),
            (string)($r['catalogName'] ?? $r['catalog'] ?? ''),
            (int)($r['correct'] ?? 0),
            (int)($r['total'] ?? 0),
            date('Y-m-d H:i', (int)($r['time'] ?? 0)),
            isset($r['puzzleTime']) ? date('Y-m-d H:i', (int) $r['puzzleTime']) : '',
            (string)($r['photo'] ?? ''),
        ];
    }

    /**
     * @param list<array<string|int>> $rows
     */
    /**
     * Convert an array of rows into a semicolon separated CSV string.
     *
     * @param list<array<string|int>> $rows
     */
    private function buildCsv(array $rows): string {
        $lines = [];
        foreach ($rows as $row) {
            $cells = array_map(static function ($v) {
                return '"' . str_replace('"', '""', (string) $v) . '"';
            }, $row);
            $lines[] = implode(';', $cells);
        }
        return implode("\n", $lines) . "\n";
    }

    private function sanitizePdfText(string $text): string {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
        if ($converted === false) {
            return preg_replace('/[^\x00-\x7F]/', '?', $text);
        }
        return $converted;
    }
}
