<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class ConfigService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

    }

    public function getJson(): ?string
    {
        $stmt = $this->pdo->query('SELECT * FROM config LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row = $this->normalizeKeys($row);
        return json_encode($row, JSON_PRETTY_PRINT);
    }

    public function getConfig(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM config LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeKeys($row) : [];
    }

    public function saveConfig(array $data): void
    {
        $keys = ['displayErrorDetails','QRUser','logoPath','pageTitle','header','subheader','backgroundColor','buttonColor','CheckAnswerButton','adminUser','adminPass','QRRestrict','competitionMode','teamResults','photoUpload','puzzleWordEnabled','puzzleWord','puzzleFeedback'];
        $filtered = array_intersect_key($data, array_flip($keys));
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM config');
        if ($filtered) {
            $cols = array_keys($filtered);
            $params = ':' . implode(', :', $cols);
            $sql = 'INSERT INTO config(' . implode(',', $cols) . ') VALUES(' . $params . ')';
            $stmt = $this->pdo->prepare($sql);
            foreach ($filtered as $k => $v) {
                if (is_bool($v)) {
                    $stmt->bindValue(':' . $k, $v, PDO::PARAM_BOOL);
                } else {
                    $stmt->bindValue(':' . $k, $v);
                }
            }
            $stmt->execute();
        }
        $this->pdo->commit();
    }

    /**
     * Normalize database column names to expected camelCase keys.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeKeys(array $row): array
    {
        $keys = ['displayErrorDetails','QRUser','logoPath','pageTitle','header','subheader','backgroundColor','buttonColor','CheckAnswerButton','adminUser','adminPass','QRRestrict','competitionMode','teamResults','photoUpload','puzzleWordEnabled','puzzleWord','puzzleFeedback'];
        $map = [];
        foreach ($keys as $k) {
            $map[strtolower($k)] = $k;
        }
        $normalized = [];
        foreach ($row as $k => $v) {
            $normalized[$map[strtolower($k)] ?? $k] = $v;
        }
        return $normalized;
    }
}
