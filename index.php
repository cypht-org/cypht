<?php

/* application dir */
define('APP_PATH', '');

/* config file location */
define('CONFIG_FILE', APP_PATH.'hm3.rc');

/* debug mode switch */
define('DEBUG_MODE', true);

/* don't let anything output content until we are ready */
ob_start();

/* show all warnings in debug mode */
if (DEBUG_MODE) {
    error_reporting(E_ALL | E_STRICT);
}

/* set default TZ */
date_default_timezone_set( 'UTC' );

/* get includes */
require APP_PATH.'lib/framework.php';

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
