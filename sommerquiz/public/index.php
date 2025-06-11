<?php
require __DIR__ . '/../vendor/autoload.php';
$app = \Slim\Factory\AppFactory::create();
(require __DIR__ . '/../src/routes.php')($app);
$app->run();
