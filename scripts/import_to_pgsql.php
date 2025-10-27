<?php

declare(strict_types=1);

$base = dirname(__DIR__);

$configFile = "$base/data/config.json";
$config = [];
if (is_readable($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?? [];
}

$dsn = getenv('POSTGRES_DSN') ?: ($config['postgres_dsn'] ?? null);
$user = getenv('POSTGRES_USER') ?: ($config['postgres_user'] ?? null);
$pass = getenv('POSTGRES_PASSWORD') ?: getenv('POSTGRES_PASS') ?: ($config['postgres_pass'] ?? null);
$db   = getenv('POSTGRES_DB') ?: ($config['postgres_db'] ?? null);

if (!$dsn || !$user || !$db) {
    fwrite(STDERR, "PostgreSQL connection parameters missing\n");
    exit(1);
}

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$pdo->beginTransaction();

// Clear existing tables to allow re-import without duplicates
$pdo->exec('TRUNCATE config, teams, results, catalogs, questions, photo_consents, events RESTART IDENTITY CASCADE');

$eventsFile = "$base/data/events.json";
$activeUid = (string)($config['event_uid'] ?? '');
if (is_readable($eventsFile)) {
    $events = json_decode(file_get_contents($eventsFile), true) ?? [];
    $firstUid = null;
    $stmt = $pdo->prepare('INSERT INTO events(uid,name,start_date,end_date,description) VALUES(?,?,?,?,?)');
    foreach ($events as $e) {
        $uid = $e['uid'] ?? bin2hex(random_bytes(16));
        if ($firstUid === null) {
            $firstUid = $uid;
        }
        $stmt->execute([
            $uid,
            $e['name'] ?? '',
            $e['start_date'] ?? date('Y-m-d\TH:i'),
            $e['end_date'] ?? date('Y-m-d\TH:i'),
            $e['description'] ?? null,
        ]);
    }
    if ($activeUid === '' && $firstUid !== null) {
        $activeUid = $firstUid;
    }
}
if ($activeUid === '') {
    $activeUid = null;
}

// Import config
$configData = array_intersect_key(
    $config,
    array_flip([
        'displayErrorDetails',
        'QRUser',
        'QRRemember',
        'logoPath',
        'pageTitle',
        'backgroundColor',
        'buttonColor',
        'CheckAnswerButton',
        'QRRestrict',
        'competitionMode',
        'teamResults',
        'photoUpload',
        'puzzleWordEnabled',
        'puzzleWord',
        'puzzleFeedback',
        'inviteText',
        'event_uid',
    ])
);
if ($configData || $activeUid !== null) {
    if ($activeUid !== null) {
        $configData['event_uid'] = $activeUid;
    }
    $cols = array_keys($configData);
    $placeholders = array_map(fn($c) => ':' . $c, $cols);
    $sql = 'INSERT INTO config(' . implode(',', $cols) . ') VALUES(' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    foreach ($configData as $k => $v) {
        if (is_bool($v)) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_BOOL);
        } else {
            $stmt->bindValue(':' . $k, $v);
        }
    }
    $stmt->execute();
    $pdo->exec(
        "SELECT setval(" .
        "pg_get_serial_sequence('config','id'), " .
        "GREATEST(1, (SELECT COALESCE(MAX(id),0) FROM config)))"
    );
}

if ($activeUid !== null) {
    $pdo->exec('DELETE FROM active_event');
    $stmt = $pdo->prepare('INSERT INTO active_event(event_uid) VALUES(?)');
    $stmt->execute([$activeUid]);
}


// Import teams
$teamsFile = "$base/data/teams.json";
if (is_readable($teamsFile)) {
    $teams = json_decode(file_get_contents($teamsFile), true) ?? [];
    $key = null;
    if (isset($teams[0]['sort_order'])) {
        $key = 'sort_order';
    } elseif (isset($teams[0]['id'])) {
        $key = 'id';
    }
    $stmt = $pdo->prepare('INSERT INTO teams(event_uid,sort_order,name) VALUES(?,?,?)');
    foreach ($teams as $i => $t) {
        $name = is_array($t) ? ($t['name'] ?? (string)$t) : (string)$t;
        $sort = $key !== null && isset($t[$key]) ? (int)$t[$key] : $i + 1;
        $stmt->execute([$activeUid, $sort, $name]);
    }
}

// Import results
$resultsFile = "$base/data/results.json";
if (is_readable($resultsFile)) {
    $results = json_decode(file_get_contents($resultsFile), true) ?? [];
    $withId = isset($results[0]['id']);
    $sql = $withId
        ? 'INSERT INTO results(id,name,catalog,attempt,correct,total,time,puzzleTime,photo,player_uid,event_uid)' .
            ' VALUES(?,?,?,?,?,?,?,?,?,?,?)'
        : 'INSERT INTO results(name,catalog,attempt,correct,total,time,puzzleTime,photo,player_uid,event_uid)' .
            ' VALUES(?,?,?,?,?,?,?,?,?,?)';
    $stmt = $pdo->prepare($sql);
    foreach ($results as $r) {
        $playerUid = null;
        if (array_key_exists('player_uid', $r) || array_key_exists('playerUid', $r)) {
            $raw = $r['player_uid'] ?? $r['playerUid'];
            if ($raw !== null) {
                $trimmed = trim((string) $raw);
                if ($trimmed !== '') {
                    $playerUid = $trimmed;
                }
            }
        }
        $params = [
            $r['name'] ?? '',
            $r['catalog'] ?? '',
            $r['attempt'] ?? 1,
            $r['correct'] ?? 0,
            $r['total'] ?? 0,
            $r['time'] ?? time(),
            $r['puzzleTime'] ?? null,
            $r['photo'] ?? null,
            $playerUid,
            $activeUid,
        ];
        if ($withId) {
            array_unshift($params, $r['id']);
        }
        $stmt->execute($params);
    }
    $pdo->exec(
        "SELECT setval(pg_get_serial_sequence('results','id'), " .
        "GREATEST(1, (SELECT COALESCE(MAX(id),0) FROM results)))"
    );
}

// Import catalogs and questions
$catalogDir = "$base/data/kataloge";
$catalogsFile = "$catalogDir/catalogs.json";
if (is_readable($catalogsFile)) {
    $catalogs = json_decode(file_get_contents($catalogsFile), true) ?? [];
    $catStmt = $pdo->prepare(
        'INSERT INTO catalogs(uid,sort_order,slug,file,name,description,' .
        'raetsel_buchstabe,comment,event_uid) VALUES(?,?,?,?,?,?,?,?,?)'
    );
    $qStmt = $pdo->prepare(
        'INSERT INTO questions(catalog_uid,type,prompt,options,answers,terms,items,sort_order)' .
        ' VALUES(?,?,?,?,?,?,?,?)'
    );
    foreach ($catalogs as $cat) {
        $catStmt->execute([
            $cat['uid'] ?? '',
            $cat['id'] ?? '',
            $cat['slug'] ?? ($cat['id'] ?? ''),
            $cat['file'] ?? '',
            $cat['name'] ?? '',
            $cat['description'] ?? null,
            $cat['raetsel_buchstabe'] ?? null,
            $cat['comment'] ?? null,
            $activeUid
        ]);
        $file = $catalogDir . '/' . ($cat['file'] ?? '');
        if (is_readable($file)) {
            $questions = json_decode(file_get_contents($file), true) ?? [];
            foreach ($questions as $i => $q) {
                $qStmt->execute([
                    $cat['uid'] ?? '',
                    $q['type'] ?? '',
                    $q['prompt'] ?? '',
                    isset($q['options']) ? json_encode($q['options']) : null,
                    isset($q['answers']) ? json_encode($q['answers']) : null,
                    isset($q['terms']) ? json_encode($q['terms']) : null,
                    isset($q['items']) ? json_encode($q['items']) : null,
                    $i + 1
                ]);
            }
        }
    }
    $pdo->exec(
        "SELECT setval(pg_get_serial_sequence('questions','id'), " .
        "GREATEST(1, (SELECT COALESCE(MAX(id),0) FROM questions)))"
    );
}

// Import photo consents
$consentFile = "$base/data/photo_consents.json";
if (is_readable($consentFile)) {
    $consents = json_decode(file_get_contents($consentFile), true) ?? [];
    $stmt = $pdo->prepare('INSERT INTO photo_consents(team,time,event_uid) VALUES(?,?,?)');
    foreach ($consents as $c) {
        $stmt->execute([
            $c['team'] ?? '',
            $c['time'] ?? 0,
            $c['event_uid'] ?? $activeUid,
        ]);
    }
    $pdo->exec(
        "SELECT setval(pg_get_serial_sequence('photo_consents','id'), " .
        "GREATEST(1, (SELECT COALESCE(MAX(id),0) FROM photo_consents)))"
    );
}

$pdo->commit();

echo "Import completed\n";
