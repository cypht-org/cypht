<?php

/* config file location */
define('CONFIG_FILE', 'hm3.rc');

/* debug mode switch */
define('DEBUG_MODE', false);

/* application dir */
define('APP_PATH', '');

/* don't let anything output content until we are ready */
ob_start();

/* show all warnings in debug mode */
if (DEBUG_MODE) {
    error_reporting(E_ALL | E_STRICT);
}

/* set default TZ */
date_default_timezone_set( 'UTC' );

/* get includes */
require APP_PATH.'lib/modules.php';
require APP_PATH.'lib/config.php';
require APP_PATH.'lib/auth.php';
require APP_PATH.'lib/session.php';
require APP_PATH.'lib/format.php';
require APP_PATH.'lib/router.php';
require APP_PATH.'lib/request.php';
require APP_PATH.'lib/cache.php';
require APP_PATH.'lib/output.php';
require APP_PATH.'lib/crypt.php';
require APP_PATH.'lib/db.php';
require APP_PATH.'lib/servers.php';
require APP_PATH.'lib/nonce.php';

/* get configuration */
$config = new Hm_Site_Config_File(CONFIG_FILE);

/* setup ini settings */
require APP_PATH.'lib/ini_set.php';

/* process request and send output to the browser */
$router = new Hm_Router();
$router->process_request($config);

/* log some debug stats about the page */
if (DEBUG_MODE) {
    Hm_Debug::load_page_stats();
    Hm_Debug::show('log');
}
?>
