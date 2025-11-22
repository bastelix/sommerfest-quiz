<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database;
use App\Service\CertificateProvisioningService;
use App\Service\DomainStartPageService;
use App\Service\MarketingDomainProvider;
use App\Support\EnvLoader;
use PDO;

EnvLoader::loadAndSet(__DIR__ . '/../.env');

$pdo = Database::connectFromEnv();
$domainService = new DomainStartPageService($pdo);
$marketingDomainProvider = new MarketingDomainProvider(static fn (): PDO => $pdo, 0);
$certificateProvisioner = new CertificateProvisioningService($domainService);

$result = $domainService->reconcileMarketingDomains($marketingDomainProvider, $certificateProvisioner);

$provisioned = $result['provisioned'];
if ($provisioned === []) {
    echo "No new marketing domains required provisioning.\n";
} else {
    echo 'Provisioned domains: ' . implode(', ', $provisioned) . "\n";
}

echo 'Resolved marketing domains: ' . implode(', ', $result['resolved_marketing_domains']) . "\n";
