<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Service\StripeService;

// Load environment variables from .env if available
\App\Support\EnvLoader::loadAndSet(__DIR__ . '/../.env');

$config = StripeService::isConfigured();
if ($config['ok']) {
    echo "Stripe configuration OK\n";
    exit(0);
}

$missing = implode(', ', $config['missing'] ?? []);
fwrite(STDERR, "Stripe configuration missing: $missing\n");
exit(1);
