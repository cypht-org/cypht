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
/* set the default since and per_source values */
$environment->define_default_constants($config);

/* check config for db auth */
if ($config->get('auth_type') != 'DB') {
    print("Incorrect usage\n\nThis script only works if DB auth is enabled in your site configuration\n\n");
    exit(1);
}

$auth = new Hm_Auth_DB($config);

$dbh = Hm_DB::connect($config);
if ($dbh) {
    try {
        $result = $dbh->query("SELECT 1 FROM hm_user LIMIT 1");
    } catch (Exception $e) {
        fwrite(STDERR, "Error: Required table 'hm_user' does not exist in the database.\n" .
            "You may need to initialize the database structure first.\n" .
            "Run: php ./scripts/setup_database.php\n");
        exit(2);
    }
} else {
    fwrite(STDERR, "Error: Unable to connect to the database.\n");
    exit(2);
}

if ($user && $pass) {
    $res = Hm_DB::execute($dbh, 'select username from hm_user where username = ?', [$user]);
    if (!empty($res)) {
        fwrite(STDOUT, "User '{$user}' already exists. Skipping creation...\n");
        exit(0);
    }

    $result = $auth->create($user, $pass);
    switch ($result) {
        case 1:
            fwrite(STDERR, "Error: Unable to create user account.\n");
            exit(2);
        case 2:
            fwrite(STDOUT, "User account created successfully.\n");
            exit(0);
        default:
            fwrite(STDERR, "Error: An unknown error occurred while trying to create user account.\n");
            exit(2);
    }
}
