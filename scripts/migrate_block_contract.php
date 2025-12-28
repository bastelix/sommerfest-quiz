<?php

declare(strict_types=1);

use App\Service\PageBlockContractMigrator;
use App\Support\EnvLoader;

require __DIR__ . '/../vendor/autoload.php';

EnvLoader::loadAndSet(__DIR__ . '/../.env');

$migrator = new PageBlockContractMigrator();
$report = $migrator->migrateAll();

$output = [
    'Total pages processed: ' . $report['total'],
    'Pages migrated: ' . $report['migrated'],
    'Pages skipped (already valid): ' . $report['skipped'],
    'Pages with migration errors: ' . $report['errors']['total'],
    '  - unknown block type: ' . $report['errors']['unknown_block_type'],
    '  - missing required data: ' . $report['errors']['missing_required_data'],
    '  - invalid variant: ' . $report['errors']['invalid_variant'],
    '  - schema violation: ' . $report['errors']['schema_violation'],
    '  - invalid json: ' . $report['errors']['invalid_json'],
];

echo implode(PHP_EOL, $output) . PHP_EOL;

if ($report['details'] !== []) {
    echo PHP_EOL . 'Migration errors:' . PHP_EOL;
    foreach ($report['details'] as $detail) {
        echo sprintf(
            '  - Page #%d [%s/%s]: %s (%s)%s',
            $detail['pageId'] ?? 0,
            $detail['namespace'] ?? 'unknown',
            $detail['slug'] ?? 'unknown',
            $detail['reason'] ?? 'unknown',
            $detail['message'] ?? 'no message provided',
            PHP_EOL
        );
    }
}

exit($report['errors']['total'] > 0 ? 1 : 0);
