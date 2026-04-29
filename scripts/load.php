<?php

/**
 * This loads all Cypht classes and functions to make manual CLI testing easier.
 * To use this loader, create a PHP file in this directory with the following:
 *
 *
 * <?php
 *
 * define('APP_PATH', dirname(dirname(__FILE__)).'/');
 * require sprintf('%s/scripts/load.php', APP_PATH);
 *
 * // test code goes here
 *
 */


if (mb_strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';
$environment = Hm_Environment::getInstance();
$environment->load();

/* Define DEBUG_MODE from environment variable */
define('DEBUG_MODE', filter_var(env('ENABLE_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
require sprintf("%s/modules/core/modules.php", APP_PATH);

foreach (scandir(sprintf('%s/modules/', APP_PATH)) as $mod) {
    if ($mod == 'core' || $mod == '.' || $mod == '..') {
        continue;
    }
    if (is_readable(sprintf("%s/modules/%s/modules.php", APP_PATH, $mod))) {
        require_once sprintf("%s/modules/%s/modules.php", APP_PATH, $mod);
    }
}

function out($str) {
    echo "\n";
    echo Hm_Debug::str($str);
    echo "\n\n";
}
