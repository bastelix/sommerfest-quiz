<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\PhotoConsentService;
use App\Service\SummaryPhotoService;
use App\Service\EventService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Exports application data and creates backup directories.
 */
class ExportController
{
    private ConfigService $config;
    private CatalogService $catalogs;
    private ResultService $results;
    private TeamService $teams;
    private PhotoConsentService $consents;
    private SummaryPhotoService $summaryPhotos;
    private EventService $events;
    private string $dataDir;
    private string $backupDir;

    /**
     * Configure dependencies and target directories.
     */
    public function __construct(
        ConfigService $config,
        CatalogService $catalogs,
        ResultService $results,
        TeamService $teams,
        PhotoConsentService $consents,
        SummaryPhotoService $summaryPhotos,
        EventService $events,
        string $dataDir,
        string $backupDir
    ) {
        $this->config = $config;
        $this->catalogs = $catalogs;
        $this->results = $results;
        $this->teams = $teams;
        $this->consents = $consents;
        $this->summaryPhotos = $summaryPhotos;
        $this->events = $events;
        $this->dataDir = rtrim($dataDir, '/');
        $this->backupDir = rtrim($backupDir, '/');
    }

    /**
     * Export current data and create a timestamped backup directory.
     */
    public function post(Request $request, Response $response): Response
    {
        $timestamp = date('Y-m-d_His');
        $backupPath = $this->backupDir . '/' . $timestamp;
        $this->exportToDir($this->dataDir);
        $this->exportToDir($backupPath);
        return $response->withStatus(204);
    }

    /**
     * Write configuration, results, teams and catalogs to the given directory.
     */
    private function exportToDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir . '/kataloge', 0777, true);
        } elseif (!is_dir($dir . '/kataloge')) {
            mkdir($dir . '/kataloge', 0777, true);
        }
        $cfg = $this->config->getJson();
        if ($cfg !== null) {
            file_put_contents($dir . '/config.json', $cfg . "\n");
        }

        $events = $this->events->getAll();
        file_put_contents(
            $dir . '/events.json',
            json_encode($events, JSON_PRETTY_PRINT) . "\n"
        );

        $teams = $this->teams->getAll();
        file_put_contents(
            $dir . '/teams.json',
            json_encode($teams, JSON_PRETTY_PRINT) . "\n"
        );

        $results = $this->results->getAll();
        file_put_contents(
            $dir . '/results.json',
            json_encode($results, JSON_PRETTY_PRINT) . "\n"
        );

        $qResults = $this->results->getQuestionRows();
        file_put_contents(
            $dir . '/question_results.json',
            json_encode($qResults, JSON_PRETTY_PRINT) . "\n"
        );

        $consents = $this->consents->getAll();
        file_put_contents(
            $dir . '/photo_consents.json',
            json_encode($consents, JSON_PRETTY_PRINT) . "\n"
        );

        $photos = $this->summaryPhotos->getAll();
        file_put_contents(
            $dir . '/summary_photos.json',
            json_encode($photos, JSON_PRETTY_PRINT) . "\n"
        );

        $catalogDir = $dir . '/kataloge';
        if (!is_dir($catalogDir)) {
            mkdir($catalogDir, 0777, true);
        }
        $catalogs = $this->catalogs->read('catalogs.json');
        if ($catalogs !== null) {
            file_put_contents($catalogDir . '/catalogs.json', $catalogs . "\n");
            $cats = json_decode($catalogs, true) ?? [];
            foreach ($cats as $cat) {
                if (!isset($cat['file'])) {
                    continue;
                }
                $file = basename((string)$cat['file']);
                $data = $this->catalogs->read($file);
                if ($data !== null) {
                    file_put_contents($catalogDir . '/' . $file, $data . "\n");
                }
            }
        }
    }
}
