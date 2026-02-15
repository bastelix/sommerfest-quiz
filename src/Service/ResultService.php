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

    /**
     * Cached list of question table columns.
     *
     * @var list<string>|null
     */
    private ?array $questionColumns = null;

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
                r.puzzleTime AS "puzzleTime", r.photo, r.player_uid AS "playerUid",
                c.name AS catalogName, c.uid AS "catalogUid"
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
            $playerUid = $this->normalizePlayerUid($row['playerUid'] ?? $row['player_uid'] ?? null);
            $row['playerUid'] = $playerUid;
            $row['player_uid'] = $playerUid;
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
            $catalogUidRaw = $row['catalogUid'] ?? $row['catalog_uid'] ?? null;
            if ($catalogUidRaw !== null && $catalogUidRaw !== '') {
                $catalogUid = (string) $catalogUidRaw;
                $row['catalogUid'] = $catalogUid;
                $row['catalog_uid'] = $catalogUid;
            } else {
                $row['catalogUid'] = null;
                $row['catalog_uid'] = null;
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
        $hasQuestionPointsColumn = $this->hasQuestionPointsColumn();
        $hasQuestionCountdownColumn = $this->hasQuestionCountdownColumn();

        $questionColumns = [
            'q.type',
            'q.prompt',
        ];
        if ($hasQuestionPointsColumn) {
            $questionColumns[] = 'q.points AS question_points';
        }
        if ($hasQuestionCountdownColumn) {
            $questionColumns[] = 'q.countdown AS question_countdown';
        }
        $questionColumns = array_merge(
            $questionColumns,
            ['q.options', 'q.answers', 'q.terms', 'q.items']
        );
        $questionSelect = implode(",\n                ", $questionColumns);

        $sql = <<<SQL
            SELECT qr.name, qr.catalog, qr.question_id, qr.attempt, qr.correct,
                qr.points, qr.time_left_sec, qr.final_points, qr.efficiency, qr.is_correct, qr.scoring_version,
                qr.answer_text, qr.photo, qr.consent, qr.player_uid AS "playerUid",
                {$questionSelect},
                c.name AS catalogName, q.catalog_uid AS "catalogUid"
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
            $playerUid = $this->normalizePlayerUid($row['playerUid'] ?? $row['player_uid'] ?? null);
            $row['playerUid'] = $playerUid;
            $row['player_uid'] = $playerUid;
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
            if ($hasQuestionPointsColumn && array_key_exists('question_points', $row)) {
                $row['questionPoints'] = $row['question_points'] !== null
                    ? (int) $row['question_points']
                    : 0;
                unset($row['question_points']);
            } elseif (!$hasQuestionPointsColumn) {
                $row['questionPoints'] = 0;
            }
            if ($hasQuestionCountdownColumn && array_key_exists('question_countdown', $row)) {
                $row['questionCountdown'] = $row['question_countdown'] !== null
                    ? (int) $row['question_countdown']
                    : null;
                unset($row['question_countdown']);
            } elseif (!$hasQuestionCountdownColumn) {
                $row['questionCountdown'] = null;
            }
            $catalogUidRaw = $row['catalogUid'] ?? $row['catalog_uid'] ?? null;
            if ($catalogUidRaw !== null && $catalogUidRaw !== '') {
                $catalogUid = (string) $catalogUidRaw;
                $row['catalogUid'] = $catalogUid;
                $row['catalog_uid'] = $catalogUid;
            } else {
                $row['catalogUid'] = null;
                $row['catalog_uid'] = null;
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
     * Determine whether the questions table exposes the requested column.
     */
    private function hasQuestionColumn(string $column): bool {
        return in_array($column, $this->getQuestionColumns(), true);
    }

    /**
     * Check if the questions table provides a points column.
     */
    private function hasQuestionPointsColumn(): bool {
        return $this->hasQuestionColumn('points');
    }

    /**
     * Check if the questions table provides a countdown column.
     */
    private function hasQuestionCountdownColumn(): bool {
        return $this->hasQuestionColumn('countdown');
    }

    /**
     * Retrieve the known columns of the questions table.
     *
     * @return list<string>
     */
    private function getQuestionColumns(): array {
        if ($this->questionColumns !== null) {
            return $this->questionColumns;
        }

        $columns = [];
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query('PRAGMA table_info(questions)');
                $rows = $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
                $columns = array_map(
                    static fn(array $row): string => (string) ($row['name'] ?? ''),
                    $rows
                );
            } else {
                $stmt = $this->pdo->query(
                    "SELECT column_name FROM information_schema.columns " .
                    "WHERE table_schema = current_schema() AND table_name = 'questions'"
                );
                $values = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
                $columns = array_map(static fn($value): string => (string) $value, $values ?: []);
            }
        } catch (PDOException $exception) {
            error_log('Failed to inspect questions table: ' . $exception->getMessage());
            $columns = [];
        }

        $columns = array_values(array_filter($columns, static fn(string $name): bool => $name !== ''));
        $this->questionColumns = $columns;

        return $this->questionColumns;
    }

    /**
     * Fetch raw entries from the question_results table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQuestionRows(string $eventUid = ''): array {
        $sql = 'SELECT name,catalog,question_id,attempt,correct,points,time_left_sec,final_points,efficiency,is_correct,scoring_version,' .
            'answer_text,photo,consent,player_uid,event_uid '
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
        $playerUid = $this->normalizePlayerUid($data['player_uid'] ?? $data['playerUid'] ?? null);
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
            'player_uid' => $playerUid,
            'expected_duration_sec' => null,
            'duration_ratio' => null,
        ];
        $stmt = $this->pdo->prepare(
            'INSERT INTO results(' .
            'name,catalog,attempt,correct,points,total,max_points,time,puzzleTime,photo,player_uid,event_uid,started_at,duration_sec,' .
            'expected_duration_sec,duration_ratio' .
            ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $answers = isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : [];
        $summary = $this->addQuestionResults($name, $catalog, $attempt, $wrong, $entry['total'], $answers, $playerUid, $eventUid);
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
            $entry['player_uid'],
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
        $entry['playerUid'] = $entry['player_uid'];
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
        ?string $playerUid = null,
        string $eventUid = ''
    ): array {
        $uid = $this->resolveCatalogUid($catalog);
        if ($uid === null) {
            return ['points' => 0, 'max' => 0, 'expectedTime' => 0, 'questionTimeUsed' => 0];
        }
        $qStmt = $this->pdo->prepare(
            "SELECT id, points, countdown FROM questions WHERE catalog_uid=? AND type<>'flip' ORDER BY sort_order"
        );
        $qStmt->execute([$uid]);
        $rows = $qStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return ['points' => 0, 'max' => 0, 'expectedTime' => 0, 'questionTimeUsed' => 0];
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO question_results(' .
            'name,catalog,question_id,attempt,correct,points,answer_text,photo,consent,player_uid,event_uid,' .
            'time_left_sec,final_points,efficiency,is_correct,scoring_version' .
            ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $finalAwarded = 0;
        $maxPoints = 0;
        $expectedTime = 0;
        $questionTimeUsed = 0;
        $answerCount = count($answers);
        $limit = min(count($rows), max($total, $answerCount));
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
            $hasAnswerEntry = array_key_exists($i, $answers);
            $rawAnswer = $hasAnswerEntry ? $answers[$i] : null;
            $answerData = is_array($rawAnswer) ? $rawAnswer : [];
            $isCorrectValue = null;
            if (array_key_exists('isCorrect', $answerData)) {
                $isCorrectValue = $this->normalizeOptionalBool($answerData['isCorrect']);
            }
            if ($isCorrectValue === null && $hasAnswerEntry && $rawAnswer === null) {
                $isCorrectValue = false;
            }
            if ($isCorrectValue === null) {
                $isCorrectValue = !in_array($i + 1, $wrongIdx, true);
            }
            $isCorrect = (bool) $isCorrectValue;
            $correct = $isCorrect ? 1 : 0;
            $basePoints = $correct === 1 ? $questionPoints : 0;
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
                $playerUid,
                $eventUid,
                $timeLeft,
                $finalPoints,
                $efficiency,
                $isCorrect ? 1 : 0,
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
    private function normalizeOptionalBool($value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round((float) $value) !== 0;
        }

        if (is_string($value)) {
            $trimmed = strtolower(trim($value));
            if ($trimmed === '') {
                return null;
            }
            if (in_array($trimmed, ['true', 'yes', 'y', 'on', '1', 't'], true)) {
                return true;
            }
            if (in_array($trimmed, ['false', 'no', 'n', 'off', '0', 'f'], true)) {
                return false;
            }
            if (is_numeric($trimmed)) {
                return (int) round((float) $trimmed) !== 0;
            }
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
     * @param mixed $value
     */
    private function normalizePlayerUid($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            $trimmed = trim((string) $value);
            return $trimmed === '' ? null : $trimmed;
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
            'durationSec' => $durationSec,
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
     * Remove stored results for the provided team names.
     *
     * @param list<string> $teamNames
     */
    public function clearTeams(array $teamNames, string $eventUid = ''): void
    {
        $normalized = [];
        foreach ($teamNames as $name) {
            $trimmed = trim((string) $name);
            if ($trimmed === '') {
                continue;
            }
            $normalized[$trimmed] = true;
        }

        if ($normalized === []) {
            return;
        }

        $names = array_keys($normalized);
        $placeholders = implode(',', array_fill(0, count($names), '?'));

        if ($eventUid !== '') {
            $params = array_merge([$eventUid], $names);
            $stmt = $this->pdo->prepare(sprintf('DELETE FROM results WHERE event_uid=? AND name IN (%s)', $placeholders));
            $stmt->execute($params);
            $stmt = $this->pdo->prepare(sprintf('DELETE FROM question_results WHERE event_uid=? AND name IN (%s)', $placeholders));
            $stmt->execute($params);

            return;
        }

        $stmt = $this->pdo->prepare(sprintf('DELETE FROM results WHERE name IN (%s)', $placeholders));
        $stmt->execute($names);
        $stmt = $this->pdo->prepare(sprintf('DELETE FROM question_results WHERE name IN (%s)', $placeholders));
        $stmt->execute($names);
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
                'name,catalog,attempt,correct,points,total,max_points,time,puzzleTime,photo,player_uid,event_uid,started_at,duration_sec,' .
                'expected_duration_sec,duration_ratio' .
                ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
        } else {
            $this->pdo->exec('DELETE FROM results');
            $stmt = $this->pdo->prepare(
                'INSERT INTO results(' .
                'name,catalog,attempt,correct,points,total,max_points,time,puzzleTime,photo,player_uid,event_uid,started_at,duration_sec,' .
                'expected_duration_sec,duration_ratio' .
                ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
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
            $playerUid = $this->normalizePlayerUid($row['player_uid'] ?? $row['playerUid'] ?? null);
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
                $playerUid,
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
                'is_correct,scoring_version,answer_text,photo,consent,player_uid,event_uid) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
        } else {
            $this->pdo->exec('DELETE FROM question_results');
            $stmt = $this->pdo->prepare(
                'INSERT INTO question_results(' .
                'name,catalog,question_id,attempt,correct,points,time_left_sec,final_points,efficiency,' .
                'is_correct,scoring_version,answer_text,photo,consent,player_uid,event_uid) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
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
            $playerUid = $this->normalizePlayerUid($row['player_uid'] ?? $row['playerUid'] ?? null);
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
                $playerUid,
                $eventUid !== '' ? $eventUid : (isset($row['event_uid']) ? (string)$row['event_uid'] : null),
            ];
            $stmt->execute($params);
        }
        $this->pdo->commit();
    }

    /**
     * Return the list of catalog identifiers a player has completed.
     *
     * @return list<string>
     */
    public function getSolvedCatalogs(string $eventUid, string $playerUid): array
    {
        if ($eventUid === '' || $playerUid === '') {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT LOWER(catalog) FROM results WHERE event_uid = ? AND player_uid = ?'
        );
        $stmt->execute([$eventUid, $playerUid]);

        /** @var list<string> $catalogs */
        $catalogs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $catalogs;
    }
}
