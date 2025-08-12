<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Service\StripeService;

// Load environment variables from .env if available
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    $vars = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($vars)) {
        foreach ($vars as $key => $value) {
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}

if (StripeService::isConfigured()) {
    echo "Stripe configuration OK\n";
    exit(0);
}

fwrite(STDERR, "Stripe configuration missing\n");
exit(1);
