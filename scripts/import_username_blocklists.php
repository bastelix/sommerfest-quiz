<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Service\UsernameBlocklistService;
use App\Support\UsernameGuard;

/**
 * @param resource $handle
 */
function detectDelimiter($handle): string
{
    $position = ftell($handle);
    $line = fgets($handle);
    $delimiter = ',';
    if ($line !== false) {
        $semicolonCount = substr_count($line, ';');
        $commaCount = substr_count($line, ',');
        if ($semicolonCount > 0 && $semicolonCount >= $commaCount) {
            $delimiter = ';';
        }
    }

    if ($position !== false) {
        fseek($handle, $position);
    }

    return $delimiter;
}

/**
 * @return list<array{term:string,category:string}>
 */
function parseCsvFile(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException(sprintf('Unable to open CSV file "%s".', $path));
    }

    try {
        $delimiter = detectDelimiter($handle);
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            return [];
        }

        $keys = array_map(static fn ($value): string => mb_strtolower(trim((string) $value)), $header);
        if (!in_array('term', $keys, true) || !in_array('category', $keys, true)) {
            throw new InvalidArgumentException(sprintf('CSV file "%s" must contain "term" and "category" columns.', $path));
        }

        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($values === null) {
                continue;
            }

            $values = array_map(static fn ($value): string => trim((string) $value), $values);
            $row = [];
            foreach ($keys as $index => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = $values[$index] ?? '';
            }

            if ($row === []) {
                continue;
            }

            $rows[] = [
                'term' => $row['term'] ?? '',
                'category' => $row['category'] ?? '',
            ];
        }

        return $rows;
    } finally {
        fclose($handle);
    }
}

/**
 * @return list<array{term:string,category:string}>
 */
function parseJsonFile(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read JSON file "%s".', $path));
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException(sprintf('JSON file "%s" must contain an array of entries.', $path));
    }

    $rows = [];
    foreach ($decoded as $index => $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException(sprintf('Entry %d in "%s" must be an object with "term" and "category".', $index, $path));
        }

        $term = isset($row['term']) ? (string) $row['term'] : '';
        $category = isset($row['category']) ? (string) $row['category'] : '';
        $rows[] = ['term' => $term, 'category' => $category];
    }

    return $rows;
}

/**
 * @param list<array{term:string,category:string}> $rows
 * @return list<array{term:string,category:string}>
 */
function deduplicateEntries(array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $categoryMap = [];
    foreach (UsernameGuard::DATABASE_CATEGORIES as $category) {
        $categoryMap[mb_strtolower($category)] = $category;
    }

    $unique = [];
    foreach ($rows as $row) {
        $term = trim($row['term']);
        $categoryRaw = trim($row['category']);

        if ($term === '' || $categoryRaw === '') {
            continue;
        }

        $categoryKey = mb_strtolower($categoryRaw);
        if (!array_key_exists($categoryKey, $categoryMap)) {
            throw new InvalidArgumentException(sprintf('Unknown category "%s" found during import.', $categoryRaw));
        }

        $category = $categoryMap[$categoryKey];
        $termKey = mb_strtolower($term);

        if (!isset($unique[$category])) {
            $unique[$category] = [];
        }

        if (!isset($unique[$category][$termKey])) {
            $unique[$category][$termKey] = [
                'term' => $term,
                'category' => $category,
            ];
        }
    }

    $result = [];
    foreach ($unique as $category => $terms) {
        foreach ($terms as $entry) {
            $result[] = $entry;
        }
    }

    return $result;
}

$arguments = $argv;
array_shift($arguments);

if ($arguments === []) {
    fwrite(STDERR, "Usage: php scripts/import_username_blocklists.php <file1> [file2 ...]\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$configPath = $baseDir . '/data/config.json';
$config = [];
if (is_readable($configPath)) {
    $config = json_decode((string) file_get_contents($configPath), true) ?? [];
}

$dsn = getenv('POSTGRES_DSN') ?: ($config['postgres_dsn'] ?? null);
$user = getenv('POSTGRES_USER') ?: ($config['postgres_user'] ?? null);
$pass = getenv('POSTGRES_PASSWORD') ?: ($config['postgres_password'] ?? null);

if (!$dsn) {
    fwrite(STDERR, "Database DSN missing. Set POSTGRES_DSN or configure data/config.json.\n");
    exit(1);
}

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $exception) {
    fwrite(STDERR, 'Database connection failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

$service = new UsernameBlocklistService($pdo);

$allRows = [];
foreach ($arguments as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, sprintf('File "%s" is not readable.' . "\n", $file));
        exit(1);
    }

    $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
    if ($extension === 'json') {
        $allRows = array_merge($allRows, parseJsonFile($file));
    } elseif ($extension === 'csv') {
        $allRows = array_merge($allRows, parseCsvFile($file));
    } else {
        fwrite(STDERR, sprintf('Unsupported file extension for "%s". Use CSV or JSON.' . "\n", $file));
        exit(1);
    }
}

if ($allRows === []) {
    fwrite(STDOUT, "No entries found in the provided files.\n");
    exit(0);
}

try {
    $entries = deduplicateEntries($allRows);
    if ($entries === []) {
        fwrite(STDOUT, "No valid entries to import after duplicate removal.\n");
        exit(0);
    }

    $service->importEntries($entries);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$categoryCounts = [];
foreach ($entries as $entry) {
    $category = $entry['category'];
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
}
ksort($categoryCounts);

$summaryParts = [];
foreach ($categoryCounts as $category => $count) {
    $summaryParts[] = sprintf('%s: %d', $category, $count);
}

$removedDuplicates = count($allRows) - count($entries);
$message = sprintf(
    'Imported %d entries across %d categories.',
    count($entries),
    count($categoryCounts)
);
if ($summaryParts !== []) {
    $message .= ' Categories -> ' . implode(', ', $summaryParts) . '.';
}
if ($removedDuplicates > 0) {
    $message .= sprintf(' Skipped %d duplicate rows.', $removedDuplicates);
}

fwrite(STDOUT, $message . "\n");
