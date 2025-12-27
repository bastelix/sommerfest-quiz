<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database;
use App\Service\CertificateProvisioningService;
use App\Service\MarketingDomainProvider;
use App\Support\EnvLoader;

EnvLoader::loadAndSet(__DIR__ . '/../.env');

$pdo = Database::connectFromEnv();
$marketingDomainProvider = new MarketingDomainProvider(static function () use ($pdo): \PDO {
    return $pdo;
});
$certificateProvisioner = new CertificateProvisioningService($marketingDomainProvider);

$certificateProvisioner->provisionAllDomains();
