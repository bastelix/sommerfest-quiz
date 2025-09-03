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
use App\Infrastructure\Database;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Imports backup data into the running application.
 */
class ImportController
{
    private CatalogService $catalogs;
    private ConfigService $config;
    private ResultService $results;
    private TeamService $teams;
    private PhotoConsentService $consents;
    private SummaryPhotoService $summaryPhotos;
    private EventService $events;
    private string $dataDir;
    private string $backupDir;
    private string $defaultDir;

    /**
     * Configure dependencies and target directories.
     */
    public function __construct(
        CatalogService $catalogs,
        ConfigService $config,
        ResultService $results,
        TeamService $teams,
        PhotoConsentService $consents,
        SummaryPhotoService $summaryPhotos,
        EventService $events,
        string $dataDir,
        string $backupDir
    ) {
        $this->catalogs = $catalogs;
        $this->config = $config;
        $this->results = $results;
        $this->teams = $teams;
        $this->consents = $consents;
        $this->summaryPhotos = $summaryPhotos;
        $this->events = $events;
        $this->dataDir = rtrim($dataDir, '/');
        $this->backupDir = rtrim($backupDir, '/');
        $this->defaultDir = dirname($this->dataDir) . '/data-default';
    }

    /**
     * Import data from the default data directory.
     */
    public function post(Request $request, Response $response): Response
    {
        return $this->importFromDir($this->dataDir, $response);
    }

    /**
     * Import demo data from the default directory used on first installation.
     */
    public function restoreDefaults(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        $schema = '';
        if (is_array($data) && isset($data['schema'])) {
            $schema = preg_replace('/[^a-z0-9_\-]/i', '', (string) $data['schema']);
        }
        if ($schema !== '') {
            $pdo = Database::connectWithSchema($schema);
            $cfg = new ConfigService($pdo);
            $tmp = new self(
                new CatalogService($pdo, $cfg),
                $cfg,
                new ResultService($pdo, $cfg),
                new TeamService($pdo, $cfg),
                new PhotoConsentService($pdo, $cfg),
                new SummaryPhotoService($pdo, $cfg),
                new EventService($pdo, $cfg),
                $this->dataDir,
                $this->backupDir
            );
            return $tmp->importFromDir($this->defaultDir, $response);
        }
        return $this->importFromDir($this->defaultDir, $response);
    }

    /**
     * Import data from a specified backup folder.
     */
    public function import(Request $request, Response $response, array $args): Response
    {
        $dir = (string)($args['name'] ?? '');
        if (
            $dir === ''
            || $dir === '.'
            || $dir === '..'
            || preg_match('/^[A-Za-z0-9._-]+$/', $dir) !== 1
        ) {
            return $response->withStatus(400);
        }

        return $this->importFromDir($this->backupDir . '/' . $dir, $response);
    }

    /**
     * Helper to import configuration, catalogs, results and more from a directory.
     */
    private function importFromDir(string $dir, Response $response): Response
    {
        $catalogDir = $dir . '/kataloge';
        $catalogsFile = $catalogDir . '/catalogs.json';
        if (!is_readable($catalogsFile)) {
            return $response->withStatus(404);
        }
        $eventsFile = $dir . '/events.json';
        if (is_readable($eventsFile)) {
            $events = json_decode((string)file_get_contents($eventsFile), true) ?? [];
            if (is_array($events)) {
                $this->events->saveAll($events);
            }
        }

        $cfgFile = $dir . '/config.json';
        if (is_readable($cfgFile)) {
            $cfg = json_decode((string)file_get_contents($cfgFile), true) ?? [];
            $this->config->saveConfig($cfg);
        }
        $teamsFile = $dir . '/teams.json';
        if (is_readable($teamsFile)) {
            $teams = json_decode((string)file_get_contents($teamsFile), true) ?? [];
            if (is_array($teams)) {
                $this->teams->saveAll($teams);
            }
        }
        $resultsFile = $dir . '/results.json';
        if (is_readable($resultsFile)) {
            $results = json_decode((string)file_get_contents($resultsFile), true) ?? [];
            if (is_array($results)) {
                $this->results->saveAll($results);
            }
        }
        $qResultsFile = $dir . '/question_results.json';
        if (is_readable($qResultsFile)) {
            $qres = json_decode((string)file_get_contents($qResultsFile), true) ?? [];
            if (is_array($qres)) {
                $this->results->saveQuestionRows($qres);
            }
        }
        $consentsFile = $dir . '/photo_consents.json';
        if (is_readable($consentsFile)) {
            $consents = json_decode((string)file_get_contents($consentsFile), true) ?? [];
            if (is_array($consents)) {
                $this->consents->saveAll($consents);
            }
        }
        $summaryFile = $dir . '/summary_photos.json';
        if (is_readable($summaryFile)) {
            $photos = json_decode((string)file_get_contents($summaryFile), true) ?? [];
            if (is_array($photos)) {
                $this->summaryPhotos->saveAll($photos);
            }
        }

        $catalogs = json_decode((string)file_get_contents($catalogsFile), true) ?? [];
        $this->catalogs->write('catalogs.json', $catalogs);
        foreach ($catalogs as $cat) {
            if (!isset($cat['file'])) {
                continue;
            }
            $file = basename((string)$cat['file']);
            $path = $catalogDir . '/' . $file;
            if (!is_readable($path)) {
                continue;
            }
            $questions = json_decode((string)file_get_contents($path), true) ?? [];
            $this->catalogs->write($file, $questions);
        }
        return $response->withStatus(204);
    }
}
