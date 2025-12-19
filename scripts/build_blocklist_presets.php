<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$sourcePath = $baseDir . '/data/username_blocklist/presets.json';
$targetDir = $baseDir . '/resources/blocklists';

$presetsMap = [
    'admin' => 'admin.csv',
    'general' => 'general.csv',
    'ns_symbols' => 'ns_symbols.csv',
    'nsfw' => 'nsfw.csv',
    'slur' => 'slur.csv',
];

$raw = file_get_contents($sourcePath);
if ($raw === false) {
    fwrite(STDERR, sprintf("Unable to read source file: %s\n", $sourcePath));
    exit(1);
}

$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    fwrite(STDERR, sprintf("Invalid JSON in %s.\n", $sourcePath));
    exit(1);
}

$presets = $decoded['presets'] ?? null;
if (!is_array($presets)) {
    fwrite(STDERR, sprintf("Missing or invalid 'presets' key in %s.\n", $sourcePath));
    exit(1);
}

$seenPresets = [];
foreach ($presets as $index => $preset) {
    if (!is_array($preset)) {
        fwrite(STDERR, sprintf("Preset entry %d must be an object.\n", $index));
        exit(1);
    }

    $presetKey = isset($preset['preset']) ? trim((string) $preset['preset']) : '';
    if ($presetKey === '') {
        fwrite(STDERR, sprintf("Preset entry %d is missing the 'preset' key.\n", $index));
        exit(1);
    }

    if (!isset($presetsMap[$presetKey])) {
        fwrite(STDERR, sprintf("Unknown preset '%s' in %s.\n", $presetKey, $sourcePath));
        exit(1);
    }

    $entries = $preset['entries'] ?? null;
    if (!is_array($entries)) {
        fwrite(STDERR, sprintf("Preset '%s' must contain an 'entries' array.\n", $presetKey));
        exit(1);
    }

    $targetPath = $targetDir . '/' . $presetsMap[$presetKey];
    $handle = fopen($targetPath, 'wb');
    if ($handle === false) {
        fwrite(STDERR, sprintf("Unable to write CSV file: %s\n", $targetPath));
        exit(1);
    }

    try {
        fputcsv($handle, ['term', 'source', 'notes'], ',', '"', '\\');

        $seenTerms = [];
        foreach ($entries as $entryIndex => $entry) {
            if (!is_array($entry)) {
                fwrite(STDERR, sprintf("Entry %d in preset '%s' must be an object.\n", $entryIndex, $presetKey));
                exit(1);
            }

            $term = isset($entry['term']) ? trim((string) $entry['term']) : '';
            if ($term === '') {
                fwrite(STDERR, sprintf("Entry %d in preset '%s' is missing a term.\n", $entryIndex, $presetKey));
                exit(1);
            }

            $termKey = mb_strtolower($term);
            if (isset($seenTerms[$termKey])) {
                continue;
            }
            $seenTerms[$termKey] = true;

            $source = isset($entry['source']) ? trim((string) $entry['source']) : '';
            $notes = isset($entry['notes']) ? trim((string) $entry['notes']) : '';

            fputcsv($handle, [$term, $source, $notes], ',', '"', '\\');
        }
    } finally {
        fclose($handle);
    }

    $seenPresets[$presetKey] = true;
}

$missing = array_diff(array_keys($presetsMap), array_keys($seenPresets));
if ($missing !== []) {
    fwrite(STDERR, sprintf("Missing presets in %s: %s\n", $sourcePath, implode(', ', $missing)));
    exit(1);
}

fwrite(STDOUT, "Preset CSV files generated successfully.\n");
