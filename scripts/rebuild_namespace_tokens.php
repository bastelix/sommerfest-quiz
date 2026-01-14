<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

\App\Support\EnvLoader::loadAndSet(__DIR__ . '/../.env');

$pdo = \App\Infrastructure\Database::connectFromEnv();
$service = new \App\Service\DesignTokenService($pdo);
$service->rebuildStylesheet();
