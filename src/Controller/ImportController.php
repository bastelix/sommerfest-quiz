<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\PhotoConsentService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ImportController
{
    private CatalogService $catalogs;
    private ConfigService $config;
    private ResultService $results;
    private TeamService $teams;
    private PhotoConsentService $consents;
    private string $dataDir;
    private string $backupDir;

    public function __construct(
        CatalogService $catalogs,
        ConfigService $config,
        ResultService $results,
        TeamService $teams,
        PhotoConsentService $consents,
        string $dataDir,
        string $backupDir
    ) {
        $this->catalogs = $catalogs;
        $this->config = $config;
        $this->results = $results;
        $this->teams = $teams;
        $this->consents = $consents;
        $this->dataDir = rtrim($dataDir, '/');
        $this->backupDir = rtrim($backupDir, '/');
    }

    public function post(Request $request, Response $response): Response
    {
        return $this->importFromDir($this->dataDir, $response);
    }

    public function migrate(Request $request, Response $response, array $args): Response
    {
        $dir = basename((string)($args['name'] ?? ''));
        if ($dir === '') {
            return $this->migrateFromDir($this->dataDir, $response);
        }
        return $this->migrateFromDir($this->backupDir . '/' . $dir, $response);
    }

    public function import(Request $request, Response $response, array $args): Response
    {
        $dir = basename((string)($args['name'] ?? ''));
        if ($dir === '') {
            return $response->withStatus(400);
        }
        return $this->importFromDir($this->backupDir . '/' . $dir, $response);
    }

    private function importFromDir(string $dir, Response $response): Response
    {
        $catalogDir = $dir . '/kataloge';
        $catalogsFile = $catalogDir . '/catalogs.json';
        if (!is_readable($catalogsFile)) {
            return $response->withStatus(404);
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
        $consentsFile = $dir . '/photo_consents.json';
        if (is_readable($consentsFile)) {
            $consents = json_decode((string)file_get_contents($consentsFile), true) ?? [];
            if (is_array($consents)) {
                $this->consents->saveAll($consents);
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

    private function migrateFromDir(string $dir, Response $response): Response
    {
        $catalogDir = $dir . '/kataloge';
        $catalogsFile = $catalogDir . '/catalogs.json';
        if (!is_readable($catalogsFile)) {
            return $response->withStatus(404);
        }
        $catalogs = json_decode((string)file_get_contents($catalogsFile), true) ?? [];
        foreach ($catalogs as &$cat) {
            if (!isset($cat['uid']) || $cat['uid'] === '') {
                $cat['uid'] = bin2hex(random_bytes(16));
            }
        }
        $this->catalogs->write('catalogs.json', $catalogs);
        foreach ($catalogs as $cat) {
            if (!isset($cat['file'])) {
                continue;
            }
            $path = $catalogDir . '/' . basename((string)$cat['file']);
            if (!is_readable($path)) {
                continue;
            }
            $questions = json_decode((string)file_get_contents($path), true) ?? [];
            $this->catalogs->write(basename((string)$cat['file']), $questions);
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
        $consentsFile = $dir . '/photo_consents.json';
        if (is_readable($consentsFile)) {
            $consents = json_decode((string)file_get_contents($consentsFile), true) ?? [];
            if (is_array($consents)) {
                $this->consents->saveAll($consents);
            }
        }
        $cfgFile = $dir . '/config.json';
        if (is_readable($cfgFile)) {
            $cfg = json_decode((string)file_get_contents($cfgFile), true) ?? [];
            $this->config->saveConfig($cfg);
        }
        return $response->withStatus(204);
    }
}
