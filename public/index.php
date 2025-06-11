<?php
require __DIR__ . '/../vendor/autoload.php';
$settings = require __DIR__ . '/../config/settings.php';
$app = \Slim\Factory\AppFactory::create();
$app->addErrorMiddleware($settings['displayErrorDetails'], true, true);
(require __DIR__ . '/../src/routes.php')($app);
$app->run();