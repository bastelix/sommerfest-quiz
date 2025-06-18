#!/usr/bin/env php
<?php
declare(strict_types=1);

// Import JSON files from data/ into PostgreSQL tables

$baseDir = dirname(__DIR__) . '/data';
$configPath = $baseDir . '/config.json';
if (!is_readable($configPath)) {
    fwrite(STDERR, "Missing config.json\n");
    exit(1);
}
$cfg = json_decode(file_get_contents($configPath), true);
if (!is_array($cfg)) {
    fwrite(STDERR, "Invalid config.json\n");
    exit(1);
}

$dsn  = getenv('POSTGRES_DSN')  ?: ($cfg['postgres_dsn'] ?? null);
$user = getenv('POSTGRES_USER') ?: ($cfg['postgres_user'] ?? null);
$pass = getenv('POSTGRES_PASS') ?: ($cfg['postgres_pass'] ?? null);
if (!$dsn || !$user) {
    fwrite(STDERR, "PostgreSQL credentials not configured\n");
    exit(1);
}

$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// CONFIG TABLE
$configCols = [
    'displayErrorDetails','QRUser','logoPath','pageTitle','header','subheader',
    'backgroundColor','buttonColor','CheckAnswerButton','adminUser','adminPass',
    'QRRestrict','competitionMode','teamResults','photoUpload','puzzleWordEnabled',
    'puzzleWord','puzzleFeedback'
];
$data = [];
foreach ($configCols as $col) {
    $data[$col] = $cfg[$col] ?? null;
}
$placeholders = ':' . implode(', :', $configCols);
$sql = 'INSERT INTO config (' . implode(', ', $configCols) . ") VALUES ($placeholders)";
$sql .= ' ON CONFLICT DO NOTHING';
$stmt = $pdo->prepare($sql);
$stmt->execute($data);

// TEAMS
$teamsPath = $baseDir . '/teams.json';
if (is_readable($teamsPath)) {
    $teams = json_decode(file_get_contents($teamsPath), true) ?: [];
    $stmt = $pdo->prepare('INSERT INTO teams(name) VALUES (:name) ON CONFLICT DO NOTHING');
    foreach ($teams as $name) {
        $stmt->execute(['name' => $name]);
    }
}

// RESULTS
$resultsPath = $baseDir . '/results.json';
if (is_readable($resultsPath)) {
    $results = json_decode(file_get_contents($resultsPath), true) ?: [];
    $sql = 'INSERT INTO results(name,catalog,attempt,correct,total,time,puzzleTime,photo) '
         . 'VALUES (:name,:catalog,:attempt,:correct,:total,:time,:puzzleTime,:photo)';
    $stmt = $pdo->prepare($sql);
    foreach ($results as $r) {
        $stmt->execute([
            'name' => $r['name'] ?? '',
            'catalog' => $r['catalog'] ?? '',
            'attempt' => $r['attempt'] ?? 1,
            'correct' => $r['correct'] ?? 0,
            'total' => $r['total'] ?? 0,
            'time' => $r['time'] ?? time(),
            'puzzleTime' => $r['puzzleTime'] ?? null,
            'photo' => $r['photo'] ?? null,
        ]);
    }
}

// PHOTO CONSENTS
$consentPath = $baseDir . '/photo_consents.json';
if (is_readable($consentPath)) {
    $consents = json_decode(file_get_contents($consentPath), true) ?: [];
    $stmt = $pdo->prepare('INSERT INTO photo_consents(team, time) VALUES (:team, :time)');
    foreach ($consents as $c) {
        $stmt->execute([
            'team' => $c['team'] ?? '',
            'time' => $c['time'] ?? time(),
        ]);
    }
}

// CATALOGS AND QUESTIONS
$catalogsPath = $baseDir . '/kataloge/catalogs.json';
if (is_readable($catalogsPath)) {
    $catalogs = json_decode(file_get_contents($catalogsPath), true) ?: [];
    $catStmt = $pdo->prepare('INSERT INTO catalogs(uid,id,file,name,description,qrcode_url,raetsel_buchstabe) '
        . 'VALUES (:uid,:id,:file,:name,:description,:qrcode_url,:raetsel_buchstabe) '
        . 'ON CONFLICT DO NOTHING');
    $qStmt = $pdo->prepare('INSERT INTO questions(catalog_id,type,prompt,options,answers,terms,items) '
        . 'VALUES (:catalog_id,:type,:prompt,:options,:answers,:terms,:items)');
    foreach ($catalogs as $cat) {
        $catStmt->execute([
            'uid' => $cat['uid'] ?? null,
            'id' => $cat['id'] ?? null,
            'file' => $cat['file'] ?? '',
            'name' => $cat['name'] ?? '',
            'description' => $cat['description'] ?? null,
            'qrcode_url' => $cat['qrcode_url'] ?? null,
            'raetsel_buchstabe' => $cat['raetsel_buchstabe'] ?? null,
        ]);
        $file = $baseDir . '/kataloge/' . $cat['file'];
        if (!is_readable($file)) {
            continue;
        }
        $questions = json_decode(file_get_contents($file), true) ?: [];
        foreach ($questions as $q) {
            $qStmt->execute([
                'catalog_id' => $cat['id'],
                'type' => $q['type'] ?? '',
                'prompt' => $q['prompt'] ?? '',
                'options' => isset($q['options']) ? json_encode($q['options']) : null,
                'answers' => isset($q['answers']) ? json_encode($q['answers']) : null,
                'terms' => isset($q['terms']) ? json_encode($q['terms']) : null,
                'items' => isset($q['items']) ? json_encode($q['items']) : null,
            ]);
        }
    }
}

echo "Import completed.\n";
