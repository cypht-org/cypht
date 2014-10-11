<?php

/* config file location */
define('CONFIG_FILE', 'hm3.rc');

/* debug mode switch */
define('DEBUG_MODE', false);

/* don't let anything output content until we are ready */
ob_start();

/* show all warnings in debug mode */
if (DEBUG_MODE) {
    error_reporting(E_ALL | E_STRICT);
}

/* set default TZ */
date_default_timezone_set( 'UTC' );

/* get includes */
require 'lib/modules.php';
require 'lib/config.php';
require 'lib/auth.php';
require 'lib/session.php';
require 'lib/format.php';
require 'lib/router.php';
require 'lib/request.php';
require 'lib/cache.php';
require 'lib/output.php';
require 'lib/crypt.php';
require 'lib/db.php';
require 'lib/servers.php';
require 'lib/nonce.php';

/* get configuration */
$config = new Hm_Site_Config_File(CONFIG_FILE);

/* setup ini settings */
require 'lib/ini_set.php';

/* process request and send output to the browser */
$router = new Hm_Router();
$router->process_request($config);

/* log some debug stats about the page */
if (DEBUG_MODE) {
    Hm_Debug::load_page_stats();
    Hm_Debug::show('log');
}
?>
