<?php

/**
 * CLI script to add a user account to the local DB
 */
if (mb_strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

if (is_array($argv) && count($argv) == 3) {
    $user = $argv[1];
    $pass = $argv[2];
}
else {
    die("Incorrect usage\n\nphp ./scripts/create_account.php <username> <password>\n\n");
}

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('WEB_ROOT', '');
require APP_PATH.'lib/define_vendor_path.php';

/* get the framework */
require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';
require APP_PATH.'lib/account_manager.php';

$environment = Hm_Environment::getInstance();
$environment->load();

/* Define DEBUG_MODE from environment variable */
define('DEBUG_MODE', filter_var(env('ENABLE_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

/* get config object */
$config = new Hm_Site_Config_File();
/* set the default since and per_source values */
$environment->define_default_constants($config);

try {
    $result = create_user_account($user, $pass, $config);
    fwrite(STDOUT, $result['message']."\n");
    exit(0);
}
catch (Throwable $e) {
    fwrite(STDERR, 'Error: '.$e->getMessage()."\n");
    exit(2);
}
