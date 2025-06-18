<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class ConfigService
{
    private PDO $pdo;
    private ?string $fallbackPath = null;

    public function __construct(PDO $pdo, ?string $fallbackPath = null)
    {
        $this->pdo = $pdo;
        $this->fallbackPath = $fallbackPath;
    }

    public function getJson(): ?string
    {
        $stmt = $this->pdo->query('SELECT * FROM config LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            if ($this->fallbackPath !== null && file_exists($this->fallbackPath)) {
                $content = file_get_contents($this->fallbackPath);
                if ($content !== false) {
                    $data = json_decode($content, true);
                    if (is_array($data)) {
                        $this->saveConfig($data);
                    }
                    return $content;
                }
            }
            return null;
        }
        unset($row['id']);
        return json_encode($row, JSON_PRETTY_PRINT);
    }

    public function getConfig(): array
    {
        $content = $this->getJson();
        if ($content === null) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }

    public function saveConfig(array $data): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('DELETE FROM config');
        $cols = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO config(' . implode(',', $cols) . ') VALUES(' . $placeholders . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        $this->pdo->commit();
    }
}
