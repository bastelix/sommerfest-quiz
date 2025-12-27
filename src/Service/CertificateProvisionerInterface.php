<?php

declare(strict_types=1);

namespace App\Service;

interface CertificateProvisionerInterface
{
    public function provisionAllDomains(): void;

    public function provisionMarketingDomain(string $domain): void;
}
