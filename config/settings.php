<?php
$path = __DIR__ . '/config.json';
if (file_exists($path)) {
    return json_decode(file_get_contents($path), true) ?? [];
}
return [];
