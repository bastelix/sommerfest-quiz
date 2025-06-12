<?php
$path = __DIR__ . '/config.json';
$settings = [];
if (file_exists($path)) {
    $settings = json_decode(file_get_contents($path), true) ?? [];
}
$settings += [
    'displayErrorDetails' => false,
];
return $settings;
