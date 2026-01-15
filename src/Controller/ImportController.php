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
    public function post(Request $request, Response $response): Response {
        return $this->importFromDir($this->dataDir, $response);
    }

    /**
     * Import demo data from the default directory used on first installation.
     */
    public function restoreDefaults(Request $request, Response $response): Response {
        $data = $this->decodeJson((string) $request->getBody(), $response);
        if ($data instanceof Response) {
            return $data;
        }
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
                new ResultService($pdo),
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
    public function import(Request $request, Response $response, array $args): Response {
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
     * Decode JSON and return data or a response with error details.
     *
     * @return mixed|Response
     */
    private function decodeJson(string $json, Response $response): mixed {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $response->getBody()->write(
                json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()])
            );
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        return $data;
    }

    /**
     * Helper to import configuration, catalogs, results and more from a directory.
     */
    private function importFromDir(string $dir, Response $response): Response {
        $catalogDir = $dir . '/kataloge';
        $catalogsFile = $catalogDir . '/catalogs.json';
        if (!is_readable($catalogsFile)) {
            return $response->withStatus(404);
        }
        $eventsFile = $dir . '/events.json';
        if (is_readable($eventsFile)) {
            $events = $this->decodeJson((string) file_get_contents($eventsFile), $response);
            if ($events instanceof Response) {
                return $events;
            }
            if (is_array($events)) {
                $this->events->saveAll($events);
            }
        }

        $cfgFile = $dir . '/config.json';
        if (is_readable($cfgFile)) {
            $cfg = $this->decodeJson((string) file_get_contents($cfgFile), $response);
            if ($cfg instanceof Response) {
                return $cfg;
            }
            $this->config->saveConfig($cfg);
        }
        $teamsFile = $dir . '/teams.json';
        if (is_readable($teamsFile)) {
            $teams = $this->decodeJson((string) file_get_contents($teamsFile), $response);
            if ($teams instanceof Response) {
                return $teams;
            }
            if (is_array($teams)) {
                $this->teams->saveAll($teams);
            }
        }
        $resultsFile = $dir . '/results.json';
        if (is_readable($resultsFile)) {
            $results = $this->decodeJson((string) file_get_contents($resultsFile), $response);
            if ($results instanceof Response) {
                return $results;
            }
            if (is_array($results)) {
                $this->results->saveAll($results);
            }
        }
        $qResultsFile = $dir . '/question_results.json';
        if (is_readable($qResultsFile)) {
            $qres = $this->decodeJson((string) file_get_contents($qResultsFile), $response);
            if ($qres instanceof Response) {
                return $qres;
            }
            if (is_array($qres)) {
                $this->results->saveQuestionRows($qres);
            }
        }
        $consentsFile = $dir . '/photo_consents.json';
        if (is_readable($consentsFile)) {
            $consents = $this->decodeJson((string) file_get_contents($consentsFile), $response);
            if ($consents instanceof Response) {
                return $consents;
            }
            if (is_array($consents)) {
                $this->consents->saveAll($consents);
            }
        }
        $summaryFile = $dir . '/summary_photos.json';
        if (is_readable($summaryFile)) {
            $photos = $this->decodeJson((string) file_get_contents($summaryFile), $response);
            if ($photos instanceof Response) {
                return $photos;
            }
            if (is_array($photos)) {
                $this->summaryPhotos->saveAll($photos);
            }
        }

        $catalogs = $this->decodeJson((string) file_get_contents($catalogsFile), $response);
        if ($catalogs instanceof Response) {
            return $catalogs;
        }
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
            $questions = $this->decodeJson((string) file_get_contents($path), $response);
            if ($questions instanceof Response) {
                return $questions;
            }
            $this->catalogs->write($file, $questions);
        }
        return $response->withStatus(204);
    }
}
