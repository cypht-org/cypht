<?php

/**
 * CLI script to build the site configuration
 */

if (strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('WEB_ROOT', '');
require APP_PATH.'lib/define_vendor_path.php';

chdir(APP_PATH);

/* get the framework */
require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';
require APP_PATH.'lib/config_builder.php';

$environment = Hm_Environment::getInstance();
$environment->load();

/* Define DEBUG_MODE from environment variable */
define('DEBUG_MODE', filter_var(env('ENABLE_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

/* create site */
try {
    build_config();
}
catch (Throwable $e) {
    echo $e->getMessage()."\n";
    exit(1);
}
