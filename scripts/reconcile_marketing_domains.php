<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database;
use App\Service\CertificateProvisioningService;
use App\Service\DomainService;
use App\Support\EnvLoader;
use PDO;

EnvLoader::loadAndSet(__DIR__ . '/../.env');

$pdo = Database::connectFromEnv();
$domainService = new DomainService($pdo);
$certificateProvisioner = new CertificateProvisioningService($domainService);

$provisioned = [];
foreach ($domainService->listDomains() as $domain) {
    $host = $domain['host'] !== '' ? $domain['host'] : $domain['normalized_host'];
    if ($host === '') {
        continue;
    }
    $certificateProvisioner->provisionMarketingDomain($host);
    $provisioned[] = $host;
}

if ($provisioned === []) {
    echo "No domains required provisioning.\n";
} else {
    echo 'Provisioned domains: ' . implode(', ', $provisioned) . "\n";
}
