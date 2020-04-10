<?php

/**
 * CLI script to add a user account to the local DB
 */
if (strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

if (is_array($argv) && count($argv) == 3) {
    $user = $argv[1];
    $pass = $argv[2];
}
else {
    die("Incorrect usage\n\nphp ./scripts/create_account.php <username> <password>\n\n");
}

/* debug mode has to be set to something or include files will die() */
define('DEBUG_MODE', false);

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');

/* get the framework */
require APP_PATH.'lib/framework.php';

/* get config object */
$config = new Hm_Site_Config_File(APP_PATH.'hm3.rc');

/* check config for db auth */
if ($config->get('auth_type') != 'DB') {
    die("Incorrect usage\n\nThis script only works if DB auth is enabled in your site configuration\n\n");
}

$auth = new Hm_Auth_DB($config);
if ($user && $pass) {
    if ($auth->create($user, $pass) === 2) {
        die("User '" . $user . "' created\n\n");
    }
    else {
        print_r(Hm_Debug::get());
        print_r(Hm_Msgs::get());
        die("An error occured when creating user  '" . $user . "'\n\n");
    }
}

?>
