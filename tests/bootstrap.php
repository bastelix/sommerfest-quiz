<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/TestCase.php';
require __DIR__ . '/Service/ArrayLogger.php';
require __DIR__ . '/Service/NullRedirectManager.php';
require __DIR__ . '/Uri.php';

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/phpunit-error.log');

if (getenv('MAIN_DOMAIN') === false || getenv('MAIN_DOMAIN') === '') {
    putenv('MAIN_DOMAIN=example.com');
    $_ENV['MAIN_DOMAIN'] = 'example.com';
}

if (getenv('MARKETING_DOMAINS') === false) {
    putenv('MARKETING_DOMAINS');
    unset($_ENV['MARKETING_DOMAINS']);
}

$rateLimitDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit_tests_' . getmypid();
\App\Application\Middleware\RateLimitMiddleware::setPersistentStore(
    new \App\Application\RateLimiting\FilesystemRateLimitStore($rateLimitDir)
);
\App\Application\Middleware\RateLimitMiddleware::resetPersistentStorage();
