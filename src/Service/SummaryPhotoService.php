<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use App\Service\ConfigService;

/**
 * Manage summary photos uploaded at quiz completion.
 */
class SummaryPhotoService
{
    private PDO $pdo;
    private ConfigService $config;

    public function __construct(PDO $pdo, ConfigService $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Store a new summary photo path.
     */
    public function add(string $name, string $path, int $time): void
    {
        $uid = $this->config->getActiveEventUid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO summary_photos(name,path,time,event_uid) VALUES(?,?,?,?)'
        );
        $stmt->execute([$name, $path, $time, $uid]);
    }

    /**
     * Retrieve stored summary photos.
     *
     * @return list<array{name:string,path:string,time:int}>
     */
    public function getAll(): array
    {
        $uid = $this->config->getActiveEventUid();
        $sql = 'SELECT name,path,time FROM summary_photos';
        $params = [];
        if ($uid !== '') {
            $sql .= ' WHERE event_uid=?';
            $params[] = $uid;
        }
        $sql .= ' ORDER BY id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
