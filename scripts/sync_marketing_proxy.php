<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use App\Service\MarketingProxySyncService;
use App\Support\EnvLoader;

require __DIR__ . '/../vendor/autoload.php';

EnvLoader::loadAndSet(__DIR__ . '/../.env');

try {
    $pdo = Database::connectFromEnv();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database connection failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$service = new MarketingProxySyncService($pdo);
$result = $service->sync();

$message = sprintf(
    'Marketing proxy sync: %d written, %d removed, reload %s',
    $result['written'],
    $result['removed'],
    $result['reloaded'] ? 'OK' : 'skipped'
);

echo $message . PHP_EOL;
