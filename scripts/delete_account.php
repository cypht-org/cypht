<?php

/**
 * CLI script to delete a user account from the local DB
 */
if (strtolower(php_sapi_name()) !== 'cli') {
    die("Must be run from the command line\n");
}

if (is_array($argv) && count($argv) == 2) {
    $user = $argv[1];
}
else {
    die("Incorrect usage\n\nphp ./scripts/delete_account.php <username>\n\n");
}

/* debug mode has to be set to something or include files will die() */
define('DEBUG_MODE', false);

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');

/* get the framework */
require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

$environment = Hm_Environment::getInstance();
$environment->load();
/* get config object */
$config = new Hm_Site_Config_File();

/* check config for db auth */
if ($config->get('auth_type') != 'DB') {
    die("Incorrect usage\n\nThis script only works if DB auth is enabled in your site configuration\n\n");
}

$auth = new Hm_Auth_DB($config);
if ($user) {
    if ($auth->delete($user)) {
        die("User deleted\n\n");
    }
    else {
        die("An error occured\n\n");
    }
}

?>
