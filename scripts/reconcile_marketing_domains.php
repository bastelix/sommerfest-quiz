<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database;
use App\Service\CertificateProvisioningService;
use App\Service\MarketingDomainProvider;
use App\Support\EnvLoader;
use PDO;

EnvLoader::loadAndSet(__DIR__ . '/../.env');

$pdo = Database::connectFromEnv();
$marketingDomainProvider = new MarketingDomainProvider(static function () use ($pdo): PDO {
    return $pdo;
});
$certificateProvisioner = new CertificateProvisioningService($marketingDomainProvider);

$provisioned = [];
foreach ($marketingDomainProvider->getMarketingDomains(stripAdmin: false) as $host) {
    $host = strtolower(trim((string) $host));
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
