<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;

/**
 * Service for persisting and retrieving quiz results.
 */
class ResultService
{
    private PDO $pdo;

    private const TIME_SCORE_ALPHA = 1.0;

    private const TIME_SCORE_FLOOR = 0.0;

    private const SCORING_VERSION = 1;

    /**
     * Inject database connection.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }


    /**
     * Fetch all stored results along with catalog names.
     */
    public function getAll(string $eventUid = ''): array {
        $sql = <<<'SQL'
            SELECT r.name, r.catalog, r.attempt, r.correct, r.points, r.total, r.max_points, r.time,
                r.started_at AS "startedAt", r.duration_sec AS "durationSec",
                r.expected_duration_sec AS "expectedDurationSec", r.duration_ratio AS "durationRatio",
                r.puzzleTime AS "puzzleTime", r.photo,
                c.name AS catalogName
            FROM results r
            LEFT JOIN catalogs c ON (
                c.uid = r.catalog
                OR CAST(c.sort_order AS TEXT) = r.catalog
                OR c.slug = r.catalog
            ) AND (c.event_uid = r.event_uid OR c.event_uid IS NULL)
        SQL;
        $params = [];
        if ($eventUid !== '') {
            $sql .= ' WHERE r.event_uid=?';
            $params[] = $eventUid;
        }
        $sql .= ' ORDER BY r.id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (array_key_exists('time', $row)) {
                $row['time'] = $row['time'] !== null ? (int) $row['time'] : null;
            }
            if (array_key_exists('startedAt', $row)) {
                $row['startedAt'] = $row['startedAt'] !== null ? (int) $row['startedAt'] : null;
                $row['started_at'] = $row['startedAt'];
            }
            if (array_key_exists('durationSec', $row)) {
                $row['durationSec'] = $row['durationSec'] !== null ? (int) $row['durationSec'] : null;
                $row['duration_sec'] = $row['durationSec'];
            }
            if (array_key_exists('expectedDurationSec', $row)) {
                $row['expectedDurationSec'] = $row['expectedDurationSec'] !== null
                    ? (int) $row['expectedDurationSec']
                    : null;
                $row['expected_duration_sec'] = $row['expectedDurationSec'];
            }
            if (array_key_exists('durationRatio', $row)) {
                $row['durationRatio'] = $row['durationRatio'] !== null
                    ? (float) $row['durationRatio']
                    : null;
                $row['duration_ratio'] = $row['durationRatio'];
            }
            $row['correct'] = isset($row['correct']) ? (int) $row['correct'] : 0;
            $row['points'] = isset($row['points']) ? (int) $row['points'] : 0;
            $row['total'] = isset($row['total']) ? (int) $row['total'] : 0;
            $row['max_points'] = isset($row['max_points']) ? (int) $row['max_points'] : 0;
            if (array_key_exists('puzzleTime', $row)) {
                if ($row['puzzleTime'] === null || $row['puzzleTime'] === '' || (int) $row['puzzleTime'] <= 0) {
                    $row['puzzleTime'] = null;
                } else {
                    $row['puzzleTime'] = (int) $row['puzzleTime'];
                }
            }
            foreach (["options","answers","terms","items"] as $k) {
                if (isset($row[$k])) {
                    $row[$k] = json_decode((string)$row[$k], true);
                } else {
                    unset($row[$k]);
                }
            }
        }
        return $rows;
    }

    /**
     * Retrieve per-question results including prompts.
     */
    public function getQuestionResults(string $eventUid = ''): array {
        $sql = <<<'SQL'
            SELECT qr.name, qr.catalog, qr.question_id, qr.attempt, qr.correct,
                qr.points, qr.time_left_sec, qr.final_points, qr.efficiency, qr.is_correct, qr.scoring_version,
                qr.answer_text, qr.photo, qr.consent,
                q.type, q.prompt, q.points AS question_points, q.countdown AS question_countdown, q.options, q.answers, q.terms, q.items,
                c.name AS catalogName
            FROM question_results qr
            LEFT JOIN questions q ON q.id = qr.question_id
            LEFT JOIN catalogs c ON (
                c.uid = q.catalog_uid
                OR CAST(c.sort_order AS TEXT) = qr.catalog
                OR c.slug = qr.catalog
            ) AND (c.event_uid = qr.event_uid OR c.event_uid IS NULL)
        SQL;
        $params = [];
        if ($eventUid !== '') {
            $sql .= ' WHERE qr.event_uid=?';
            $params[] = $eventUid;
        }
        $sql .= ' ORDER BY qr.id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['points'] = isset($row['points']) ? (int) $row['points'] : 0;
            if (array_key_exists('time_left_sec', $row)) {
                $row['timeLeftSec'] = $row['time_left_sec'] !== null ? (int) $row['time_left_sec'] : null;
                unset($row['time_left_sec']);
            }
            if (array_key_exists('final_points', $row)) {
                $row['finalPoints'] = (int) $row['final_points'];
                unset($row['final_points']);
            }
            if (array_key_exists('efficiency', $row)) {
                $row['efficiency'] = isset($row['efficiency']) ? (float) $row['efficiency'] : 0.0;
            }
            if (array_key_exists('is_correct', $row)) {
                $row['isCorrect'] = $row['is_correct'] !== null ? (bool) $row['is_correct'] : null;
                unset($row['is_correct']);
            }
            if (array_key_exists('scoring_version', $row)) {
                $row['scoringVersion'] = (int) $row['scoring_version'];
                unset($row['scoring_version']);
            }
            if (isset($row['question_points'])) {
                $row['questionPoints'] = (int) $row['question_points'];
                unset($row['question_points']);
            }
            if (array_key_exists('question_countdown', $row)) {
                $row['questionCountdown'] = $row['question_countdown'] !== null
                    ? (int) $row['question_countdown']
                    : null;
                unset($row['question_countdown']);
            }
            foreach (["options", "answers", "terms", "items"] as $k) {
                if (isset($row[$k])) {
                    $row[$k] = json_decode((string) $row[$k], true);
                } else {
                    unset($row[$k]);
                }
            }
        }
        return $rows;
    }

    /**
     * Fetch raw entries from the question_results table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQuestionRows(string $eventUid = ''): array {
        $sql = 'SELECT name,catalog,question_id,attempt,correct,points,time_left_sec,final_points,efficiency,is_correct,scoring_version,' .
            'answer_text,photo,consent,event_uid '
            . 'FROM question_results';
        $params = [];
        if ($eventUid !== '') {
            $sql .= ' WHERE event_uid=?';
            $params[] = $eventUid;
        }
        $sql .= ' ORDER BY id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check whether a result exists for the given player and catalog.
     */
    public function exists(string $name, string $catalog, string $eventUid = ''): bool {
        $sql = 'SELECT 1 FROM results WHERE name=? AND catalog=?';
        $params = [$name, $catalog];
        if ($eventUid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $eventUid;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function add(array $data, string $eventUid = ''): array {
        $name = (string)($data['name'] ?? '');
        $catalog = (string)($data['catalog'] ?? '');
        $wrong = array_map('intval', $data['wrong'] ?? []);
        $sql = 'SELECT COALESCE(MAX(attempt),0) FROM results WHERE name=? AND catalog=?';
        $params = [$name, $catalog];
        if ($eventUid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $eventUid;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $attempt = (int)$stmt->fetchColumn() + 1;
        $now = time();
        $timing = $this->normalizeAttemptTiming($data, $now);

        $entry = [
            'name' => $name,
            'catalog' => $catalog,
            'attempt' => $attempt,
            'correct' => (int)($data['correct'] ?? 0),
            'points' => 0,
            'total' => (int)($data['total'] ?? 0),
            'max_points' => 0,
            'time' => $timing['time'],
            'started_at' => $timing['startedAt'],
            'duration_sec' => $timing['durationSec'],
            'puzzleTime' => isset($data['puzzleTime']) ? (int)$data['puzzleTime'] : null,
            'photo' => isset($data['photo']) ? (string)$data['photo'] : null,
            'expected_duration_sec' => null,
            'duration_ratio' => null,
        ];
        $stmt = $this->pdo->prepare(
            'INSERT INTO results(' .
            'name,catalog,attempt,correct,points,total,max_points,time,puzzleTime,photo,event_uid,started_at,duration_sec,' .
            'expected_duration_sec,duration_ratio' .
            ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $answers = isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : [];
        $summary = $this->addQuestionResults($name, $catalog, $attempt, $wrong, $entry['total'], $answers, $eventUid);
        $entry['points'] = $summary['points'];
        $entry['max_points'] = $summary['max'];
        $expectedDuration = $summary['expectedTime'] > 0 ? (int) round($summary['expectedTime']) : null;
        $questionTimeUsed = $summary['questionTimeUsed'];
        if ($expectedDuration !== null && $expectedDuration < 0) {
            $expectedDuration = null;
        }
        $consumed = $entry['duration_sec'];
        if ($consumed === null && $expectedDuration !== null) {
            $consumed = (int) round($questionTimeUsed);
        }
        if ($consumed !== null) {
            $entry['duration_sec'] = $consumed;
        }
        $ratio = null;
        if ($expectedDuration !== null && $expectedDuration > 0 && $consumed !== null) {
            $ratio = $consumed / $expectedDuration;
        }
        $entry['expected_duration_sec'] = $expectedDuration;
        $entry['duration_ratio'] = $ratio;
        $stmt->execute([
            $entry['name'],
            $entry['catalog'],
            $entry['attempt'],
            $entry['correct'],
            $entry['points'],
            $entry['total'],
            $entry['max_points'],
            $entry['time'],
            $entry['puzzleTime'],
            $entry['photo'],
            $eventUid !== '' ? $eventUid : null,
            $entry['started_at'],
            $entry['duration_sec'],
            $entry['expected_duration_sec'],
            $entry['duration_ratio'],
        ]);
        $entry['startedAt'] = $entry['started_at'];
        $entry['durationSec'] = $entry['duration_sec'];
        $entry['expectedDurationSec'] = $entry['expected_duration_sec'];
        $entry['durationRatio'] = $entry['duration_ratio'];
        return $entry;
    }

    /**
     * Store individual question results.
     *
     * @param list<int> $wrongIdx
     * @return array{points:int,max:int,expectedTime:int,questionTimeUsed:int}
     */
    private function addQuestionResults(
        string $name,
        string $catalog,
        int $attempt,
        array $wrongIdx,
        int $total,
        array $answers = [],
        string $eventUid = ''
    ): array {
        $uid = $this->resolveCatalogUid($catalog);
        if ($uid === null) {
            return ['points' => 0, 'max' => 0];
        }
        $qStmt = $this->pdo->prepare(
            "SELECT id, points, countdown FROM questions WHERE catalog_uid=? AND type<>'flip' ORDER BY sort_order"
        );
        $qStmt->execute([$uid]);
        $rows = $qStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return ['points' => 0, 'max' => 0];
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO question_results(' .
            'name,catalog,question_id,attempt,correct,points,answer_text,photo,consent,event_uid,' .
            'time_left_sec,final_points,efficiency,is_correct,scoring_version' .
            ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $finalAwarded = 0;
        $maxPoints = 0;
        $expectedTime = 0;
        $questionTimeUsed = 0;
        $limit = min(count($rows), $total);
        for ($i = 0; $i < $limit; $i++) {
            $row = $rows[$i];
            $qid = (int)$row['id'];
            $questionPoints = isset($row['points']) ? (int)$row['points'] : 1;
            if ($questionPoints < 0) {
                $questionPoints = 0;
            } elseif ($questionPoints > 10000) {
                $questionPoints = 10000;
            }
            $questionTime = isset($row['countdown']) ? (int)$row['countdown'] : 0;
            if ($questionTime < 0) {
                $questionTime = 0;
            }
            $maxPoints += $questionPoints;
            $correct = in_array($i + 1, $wrongIdx, true) ? 0 : 1;
            $basePoints = $correct === 1 ? $questionPoints : 0;
            $answerData = $answers[$i] ?? [];
            if (!is_array($answerData)) {
                $answerData = [];
            }
            $rawTimeLeft = $answerData['timeLeftSec'] ?? $answerData['time_left_sec'] ?? null;
            $timeLeft = null;
            if ($rawTimeLeft !== null && $rawTimeLeft !== '') {
                if (is_numeric($rawTimeLeft)) {
                    $timeLeft = (int)$rawTimeLeft;
                } elseif (is_string($rawTimeLeft)) {
                    $timeLeft = (int) round((float)$rawTimeLeft);
                }
            }
            if ($questionTime > 0) {
                $timeLeft = $timeLeft === null ? 0 : $timeLeft;
                $timeLeft = max(0, min($timeLeft, $questionTime));
                $expectedTime += $questionTime;
                $used = $questionTime - $timeLeft;
                if ($used < 0) {
                    $used = 0;
                } elseif ($used > $questionTime) {
                    $used = $questionTime;
                }
                $questionTimeUsed += $used;
            } else {
                $timeLeft = null;
            }
            [$finalPoints, $efficiency] = $this->computeTimedScore(
                $questionPoints,
                $questionTime,
                $timeLeft,
                $correct === 1
            );
            $finalAwarded += $finalPoints;
            $text = isset($answerData['text']) ? (string)$answerData['text'] : null;
            $photo = isset($answerData['photo']) ? (string)$answerData['photo'] : null;
            $consent = isset($answerData['consent']) ? (int)((bool)$answerData['consent']) : null;
            $ins->execute([
                $name,
                $catalog,
                $qid,
                $attempt,
                $correct,
                $basePoints,
                $text,
                $photo,
                $consent,
                $eventUid,
                $timeLeft,
                $finalPoints,
                $efficiency,
                $correct === 1,
                self::SCORING_VERSION,
            ]);
        }
        return [
            'points' => $finalAwarded,
            'max' => $maxPoints,
            'expectedTime' => $expectedTime,
            'questionTimeUsed' => $questionTimeUsed,
        ];
    }

    /**
     * Resolve the canonical catalog UID for the provided identifier.
     */
    private function resolveCatalogUid(string $catalog): ?string
    {
        $normalized = trim($catalog);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT uid FROM catalogs WHERE uid=? OR CAST(sort_order AS TEXT)=? OR slug=? LIMIT 1'
        );
        $stmt->execute([$normalized, $normalized, $normalized]);
        $uid = $stmt->fetchColumn();
        if ($uid !== false && $uid !== null && $uid !== '') {
            return (string) $uid;
        }

        $stmt = $this->pdo->prepare(
            'SELECT uid FROM catalogs WHERE LOWER(uid)=LOWER(?) OR LOWER(slug)=LOWER(?) LIMIT 1'
        );
        $stmt->execute([$normalized, $normalized]);
        $uid = $stmt->fetchColumn();
        if ($uid !== false && $uid !== null && $uid !== '') {
            return (string) $uid;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function normalizeOptionalInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (is_numeric($trimmed)) {
                return (int) round((float) $trimmed);
            }
        }

        return null;
    }

    /**
     * Normalise start, end and duration timestamps for a quiz attempt.
     *
     * @param array<string,mixed> $data
     * @return array{time:int,startedAt:?int,durationSec:?int}
     */
    private function normalizeAttemptTiming(array $data, ?int $fallbackTime = null): array
    {
        $timeRaw = $data['time'] ?? $data['finished_at'] ?? $data['finishedAt'] ?? null;
        $finishedAt = $this->normalizeOptionalInt($timeRaw);
        if ($finishedAt === null) {
            $finishedAt = $fallbackTime ?? time();
        }

        $startedRaw = $data['started_at'] ?? $data['startedAt'] ?? null;
        $startedAt = $this->normalizeOptionalInt($startedRaw);

        $durationRaw = $data['duration_sec'] ?? $data['durationSec'] ?? null;
        $durationSec = $this->normalizeOptionalInt($durationRaw);

        if ($durationSec === null && $startedAt !== null) {
            $durationSec = $finishedAt - $startedAt;
        }

        if ($durationSec !== null) {
            if ($durationSec < 0) {
                $durationSec = 0;
            }
            if ($startedAt === null) {
                $startedAt = $finishedAt - $durationSec;
            }
        }

        if ($startedAt !== null && $startedAt > $finishedAt) {
            $startedAt = $finishedAt;
        }

        if ($startedAt !== null && $startedAt < 0) {
            $startedAt = 0;
        }

        return [
            'time' => $finishedAt,
            'startedAt' => $startedAt,
            'durationSec' => $durationSec !== null && $durationSec >= 0 ? $durationSec : null,
        ];
    }

    /**
     * Compute time-adjusted score and efficiency for a question.
     *
     * @param int $basePoints configured points for the question
     * @param int $totalTime countdown duration in seconds
     * @param int|null $timeLeft remaining seconds when the answer was submitted
     * @param bool $isCorrect whether the player answered correctly
     * @return array{int,float} [finalPoints, efficiency]
     */
    private function computeTimedScore(int $basePoints, int $totalTime, ?int $timeLeft, bool $isCorrect): array
    {
        if ($basePoints <= 0 || !$isCorrect) {
            return [0, 0.0];
        }

        if ($totalTime <= 0) {
            return [$basePoints, 1.0];
        }

        $clampedTimeLeft = $timeLeft ?? 0;
        if ($clampedTimeLeft < 0) {
            $clampedTimeLeft = 0;
        } elseif ($clampedTimeLeft > $totalTime) {
            $clampedTimeLeft = $totalTime;
        }

        $ratio = $clampedTimeLeft / $totalTime;

        $multiplier = max(pow($ratio, self::TIME_SCORE_ALPHA), self::TIME_SCORE_FLOOR);
        $finalPoints = (int) round($basePoints * $multiplier);
        if ($finalPoints < 0) {
            $finalPoints = 0;
        } elseif ($finalPoints > $basePoints) {
            $finalPoints = $basePoints;
        }

        return [$finalPoints, $ratio];
    }

    /**
     * Remove all result entries including per-question logs.
     */
    public function clear(string $eventUid = ''): void {
        if ($eventUid !== '') {
            $del = $this->pdo->prepare('DELETE FROM results WHERE event_uid=?');
            $del->execute([$eventUid]);
            $del2 = $this->pdo->prepare('DELETE FROM question_results WHERE event_uid=?');
            $del2->execute([$eventUid]);
        } else {
            $this->pdo->exec('DELETE FROM results');
            $this->pdo->exec('DELETE FROM question_results');
        }
    }

    /**
     * Mark the puzzle word as solved for the latest entry of the given user.
     */
    public function markPuzzle(string $name, string $catalog, int $time, string $eventUid = ''): bool {
        $sql = 'SELECT id, puzzleTime AS "puzzleTime" FROM results WHERE name=? AND catalog=?';
        $params = [$name, $catalog];
        if ($eventUid !== '') {
            $sql .= ' AND event_uid=?';
            $params[] = $eventUid;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['puzzleTime'] === null) {
                $upd = $this->pdo->prepare('UPDATE results SET puzzleTime=? WHERE id=?');
                $upd->execute([$time, $row['id']]);
            }
            return true;
        }
        return false;
    }

    /**
     * Associate a photo path with the latest result entry for the user.
     */
    public function setPhoto(string $name, string $catalog, string $path, string $eventUid = ''): void {
        $baseSql = 'SELECT id FROM results WHERE name=? AND catalog=?';
        $params = [$name, $catalog];
        $id = false;

        if ($eventUid !== '') {
            $sql = $baseSql . ' AND event_uid=? ORDER BY id DESC LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([...$params, $eventUid]);
            $id = $stmt->fetchColumn();
        }

        if ($id === false) {
            $sql = $baseSql . ' ORDER BY id DESC LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $id = $stmt->fetchColumn();
        }

        if ($id !== false) {
            $upd = $this->pdo->prepare('UPDATE results SET photo=? WHERE id=?');
            $upd->execute([$path, $id]);
        }
    }

    /**
     * Replace all results with the provided list.
     *
     * @param list<array<string, mixed>> $results
     */
    public function saveAll(array $results, string $eventUid = ''): void {
        $this->pdo->beginTransaction();
        if ($eventUid !== '') {
            $del = $this->pdo->prepare('DELETE FROM results WHERE event_uid=?');
            $del->execute([$eventUid]);
            $stmt = $this->pdo->prepare(
                'INSERT INTO results(' .
                'name,catalog,attempt,correct,points,total,max_points,time,puzzleTime,photo,event_uid,started_at,duration_sec,' .
                'expected_duration_sec,duration_ratio' .
                ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
        } else {
            $this->pdo->exec('DELETE FROM results');
            $stmt = $this->pdo->prepare(
                'INSERT INTO results(' .
                'name,catalog,attempt,correct,points,total,max_points,time,puzzleTime,photo,event_uid,started_at,duration_sec,' .
                'expected_duration_sec,duration_ratio' .
                ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
        }
        foreach ($results as $row) {
            $timing = $this->normalizeAttemptTiming($row);
            $expectedRaw = $row['expected_duration_sec'] ?? $row['expectedDurationSec'] ?? null;
            $expectedDuration = $this->normalizeOptionalInt($expectedRaw);
            if ($expectedDuration !== null && $expectedDuration < 0) {
                $expectedDuration = null;
            }
            $ratioRaw = $row['duration_ratio'] ?? $row['durationRatio'] ?? null;
            $ratio = null;
            if ($ratioRaw !== null && $ratioRaw !== '') {
                if (is_numeric($ratioRaw)) {
                    $ratio = (float) $ratioRaw;
                }
            }
            $params = [
                (string)($row['name'] ?? ''),
                (string)($row['catalog'] ?? ''),
                (int)($row['attempt'] ?? 1),
                (int)($row['correct'] ?? 0),
                (int)($row['points'] ?? 0),
                (int)($row['total'] ?? 0),
                (int)($row['max_points'] ?? 0),
                $timing['time'],
                isset($row['puzzleTime']) ? (int)$row['puzzleTime'] : null,
                isset($row['photo']) ? (string)$row['photo'] : null,
                $eventUid !== '' ? $eventUid : (isset($row['event_uid']) ? (string)$row['event_uid'] : null),
                $timing['startedAt'],
                $timing['durationSec'],
                $expectedDuration,
                $ratio,
            ];
            $stmt->execute($params);
        }
        $this->pdo->commit();
    }

    /**
     * Replace question_results with the provided list.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function saveQuestionRows(array $rows, string $eventUid = ''): void {
        $this->pdo->beginTransaction();
        if ($eventUid !== '') {
            $del = $this->pdo->prepare('DELETE FROM question_results WHERE event_uid=?');
            $del->execute([$eventUid]);
            $stmt = $this->pdo->prepare(
                'INSERT INTO question_results(' .
                'name,catalog,question_id,attempt,correct,points,time_left_sec,final_points,efficiency,' .
                'is_correct,scoring_version,answer_text,photo,consent,event_uid) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
        } else {
            $this->pdo->exec('DELETE FROM question_results');
            $stmt = $this->pdo->prepare(
                'INSERT INTO question_results(' .
                'name,catalog,question_id,attempt,correct,points,time_left_sec,final_points,efficiency,' .
                'is_correct,scoring_version,answer_text,photo,consent,event_uid) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
        }
        foreach ($rows as $row) {
            $timeLeft = $row['time_left_sec'] ?? $row['timeLeftSec'] ?? null;
            if ($timeLeft !== null && $timeLeft !== '') {
                $timeLeft = (int) $timeLeft;
            } else {
                $timeLeft = null;
            }
            $finalPoints = (int) ($row['final_points'] ?? $row['finalPoints'] ?? $row['points'] ?? 0);
            $efficiencyRaw = $row['efficiency'] ?? null;
            $efficiency = $efficiencyRaw !== null ? (float) $efficiencyRaw : ((int)($row['correct'] ?? 0) === 1 ? 1.0 : 0.0);
            $isCorrectRaw = $row['is_correct'] ?? $row['isCorrect'] ?? null;
            $isCorrect = $isCorrectRaw === null ? (int)($row['correct'] ?? 0) === 1 : (bool) $isCorrectRaw;
            $scoringVersion = (int) ($row['scoring_version'] ?? $row['scoringVersion'] ?? self::SCORING_VERSION);
            $params = [
                (string)($row['name'] ?? ''),
                (string)($row['catalog'] ?? ''),
                (int)($row['question_id'] ?? 0),
                (int)($row['attempt'] ?? 1),
                (int)($row['correct'] ?? 0),
                (int)($row['points'] ?? 0),
                $timeLeft,
                $finalPoints,
                $efficiency,
                $isCorrect,
                $scoringVersion,
                isset($row['answer_text']) ? (string)$row['answer_text'] : null,
                isset($row['photo']) ? (string)$row['photo'] : null,
                isset($row['consent']) ? (int)((bool)$row['consent']) : null,
                $eventUid !== '' ? $eventUid : (isset($row['event_uid']) ? (string)$row['event_uid'] : null),
            ];
            $stmt->execute($params);
        }
        $this->pdo->commit();
    }
}
