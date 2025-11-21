<?php

define('CYPHT_VERSION', '3.0');
define('CYPHT_BRANCH', 'dev'); // stable, beta, dev

$installedVersion = env('CYPHT_INSTALLED_VERSION');

$needUpgrade = false;

if (! $installedVersion && CYPHT_BRANCH !== 'dev') {
    $needUpgrade = true;
}

if ($installedVersion && version_compare($installedVersion, CYPHT_VERSION, '<')) {
    $needUpgrade = true;
}

$fileContent = file_get_contents(APP_PATH . '.env');
if ($fileContent) {
    if (strpos($fileContent, 'CYPHT_INSTALLED_VERSION=') === false) {
        $fileContent .= "\nCYPHT_INSTALLED_VERSION=" . CYPHT_VERSION . "\n";
    } else {
        $fileContent = preg_replace('/CYPHT_INSTALLED_VERSION=.*/', 'CYPHT_INSTALLED_VERSION=' . CYPHT_VERSION, $fileContent);
    }
    file_put_contents(APP_PATH . '.env', $fileContent);
}

define('CYPHT_NEED_UPGRADE', $needUpgrade);
