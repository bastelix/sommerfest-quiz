<?php

declare(strict_types=1);

namespace App\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Central allocator for curated team names with reservation support.
 */
class TeamNameService
{
    private PDO $pdo;

    /** @var array<int, string> */
    private array $adjectives = [];

    /** @var array<int, string> */
    private array $nouns = [];

    private int $lexiconVersion = 1;

    private int $reservationTtlSeconds;

    /** @var array<int, string>|null */
    private ?array $orderedNames = null;

    public function __construct(PDO $pdo, string $lexiconPath, int $reservationTtlSeconds = 600)
    {
        $this->pdo = $pdo;
        $this->reservationTtlSeconds = max(60, $reservationTtlSeconds);
        $this->loadLexicon($lexiconPath);
    }

    public function getLexiconVersion(): int
    {
        return $this->lexiconVersion;
    }

    public function getTotalCombinations(): int
    {
        return count($this->adjectives) * count($this->nouns);
    }

    /**
     * Reserve a name for the given event.
     *
     * @return array{
     *     name: string,
     *     token: string,
     *     expires_at: string,
     *     lexicon_version: int,
     *     total: int,
     *     remaining: int,
     *     fallback: bool
     * }
     */
    public function reserve(string $eventId): array
    {
        if ($eventId === '') {
            throw new InvalidArgumentException('eventId must not be empty');
        }

        $this->releaseExpiredReservations($eventId);

        $names = $this->getOrderedNames();
        $totalNames = count($names);
        if ($totalNames === 0) {
            return $this->reserveFallback($eventId);
        }

        $startIndex = $this->randomStartIndex($totalNames);

        for ($offset = 0; $offset < $totalNames; $offset++) {
            $index = ($startIndex + $offset) % $totalNames;
            $name = $names[$index];
            $token = bin2hex(random_bytes(16));
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO team_names (event_id, name, lexicon_version, reservation_token) VALUES (?,?,?,?)'
                );
                $stmt->execute([$eventId, $name, $this->lexiconVersion, $token]);

                return $this->formatReservationResponse($eventId, $name, $token, false);
            } catch (PDOException $exception) {
                if ($this->isUniqueViolation($exception)) {
                    continue;
                }
                throw $exception;
            }
        }

        return $this->reserveFallback($eventId);
    }

    /**
     * Confirm usage of a reserved name.
     *
     * @return array{name: string, fallback: bool}|null
     */
    public function confirm(string $eventId, string $token, ?string $expectedName = null): ?array
    {
        if ($eventId === '' || $token === '') {
            throw new InvalidArgumentException('eventId and token are required');
        }

        $this->releaseExpiredReservations($eventId);

        $stmt = $this->pdo->prepare(
            'SELECT id, name, fallback, assigned_at FROM team_names '
            . 'WHERE event_id = ? AND reservation_token = ? AND released_at IS NULL'
        );
        $stmt->execute([$eventId, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $name = (string) $row['name'];
        if ($expectedName !== null && $this->normalize($expectedName) !== $this->normalize($name)) {
            return null;
        }

        if ($row['assigned_at'] === null) {
            $update = $this->pdo->prepare('UPDATE team_names SET assigned_at = CURRENT_TIMESTAMP WHERE id = ?');
            $update->execute([(int) $row['id']]);
        }

        return [
            'name' => $name,
            'fallback' => (bool) $row['fallback'],
        ];
    }

    public function release(string $eventId, string $token): bool
    {
        if ($eventId === '' || $token === '') {
            throw new InvalidArgumentException('eventId and token are required');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE team_names SET released_at = CURRENT_TIMESTAMP '
            . 'WHERE event_id = ? AND reservation_token = ? AND released_at IS NULL'
        );
        $stmt->execute([$eventId, $token]);
        return $stmt->rowCount() > 0;
    }

    public function releaseByName(string $eventId, string $name): void
    {
        if ($eventId === '' || $name === '') {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE team_names SET released_at = CURRENT_TIMESTAMP '
            . 'WHERE event_id = ? AND name = ? AND released_at IS NULL'
        );
        $stmt->execute([$eventId, $name]);
    }

    private function reserveFallback(string $eventId): array
    {
        $token = bin2hex(random_bytes(16));
        $name = 'Gast-' . strtoupper(substr($token, 0, 5));
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO team_names (event_id, name, lexicon_version, reservation_token, fallback) '
                . 'VALUES (?,?,?,?,TRUE)'
            );
            $stmt->execute([$eventId, $name, $this->lexiconVersion, $token]);
        } catch (PDOException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return $this->reserveFallback($eventId);
            }
            throw $exception;
        }

        $response = $this->formatReservationResponse($eventId, $name, $token, true);
        $response['remaining'] = 0;
        return $response;
    }

    /**
     * @return array{
     *     name: string,
     *     token: string,
     *     expires_at: string,
     *     lexicon_version: int,
     *     total: int,
     *     remaining: int,
     *     fallback: bool
     * }
     */
    private function formatReservationResponse(string $eventId, string $name, string $token, bool $fallback): array
    {
        $expiresAt = $this->now()->add(new DateInterval('PT' . $this->reservationTtlSeconds . 'S'));
        $active = $this->countActiveAssignments($eventId);

        return [
            'name' => $name,
            'token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'lexicon_version' => $this->lexiconVersion,
            'total' => $this->getTotalCombinations(),
            'remaining' => max(0, $this->getTotalCombinations() - $active),
            'fallback' => $fallback,
        ];
    }

    private function countActiveAssignments(string $eventId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM team_names WHERE event_id = ? AND released_at IS NULL');
        $stmt->execute([$eventId]);
        return (int) $stmt->fetchColumn();
    }

    private function releaseExpiredReservations(string $eventId): void
    {
        $threshold = $this->now()->sub(new DateInterval('PT' . $this->reservationTtlSeconds . 'S'));
        $stmt = $this->pdo->prepare(
            'UPDATE team_names SET released_at = CURRENT_TIMESTAMP '
            . 'WHERE event_id = ? AND released_at IS NULL AND assigned_at IS NULL AND reserved_at <= ?'
        );
        $stmt->execute([$eventId, $threshold->format('Y-m-d H:i:sP')]);
    }

    /**
     * @return array<int, string>
     */
    private function getOrderedNames(): array
    {
        if ($this->orderedNames !== null) {
            return $this->orderedNames;
        }
        $names = [];
        foreach ($this->adjectives as $adj) {
            foreach ($this->nouns as $noun) {
                $names[] = trim($adj . ' ' . $noun);
            }
        }
        $this->orderedNames = $names;
        return $names;
    }

    private function loadLexicon(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Team name lexicon not found at %s', $path));
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read team name lexicon');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid team name lexicon');
        }
        $adjectives = array_values(array_unique(array_map('strval', $data['adjectives'] ?? [])));
        $nouns = array_values(array_unique(array_map('strval', $data['nouns'] ?? [])));
        if ($adjectives === [] || $nouns === []) {
            throw new RuntimeException('Team name lexicon requires adjectives and nouns');
        }
        sort($adjectives, SORT_NATURAL | SORT_FLAG_CASE);
        sort($nouns, SORT_NATURAL | SORT_FLAG_CASE);
        $this->adjectives = $adjectives;
        $this->nouns = $nouns;
        $this->lexiconVersion = is_int($data['version'] ?? null) ? (int) $data['version'] : 1;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    protected function randomStartIndex(int $total): int
    {
        if ($total <= 1) {
            return 0;
        }

        try {
            return random_int(0, $total - 1);
        } catch (Throwable $exception) {
            return 0;
        }
    }

    private function isUniqueViolation(PDOException $exception): bool
    {
        $code = $exception->getCode();
        return $code === '23505' || $code === '23000';
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
