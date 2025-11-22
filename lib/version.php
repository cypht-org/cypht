<?php

define('CYPHT_VERSION', '2.5.0');

// $releases = json_decode(file_get_contents('https://github.com/cypht-org/cypht/blob/master/releases.json?raw=true'), true);
$releases = json_decode(file_get_contents(APP_PATH.'releases.json'), true); // TODO: This should be replaced by the line above in production
$latestRelease = end($releases['release']);

if (version_compare(CYPHT_VERSION, $latestRelease['version'], '<')) {
    $needUpgrade = true;
} else {
    $needUpgrade = false;
}

define('CYPHT_NEED_UPGRADE', $needUpgrade);
define('CYPHT_LATEST_VERSION', $latestRelease['version']);
