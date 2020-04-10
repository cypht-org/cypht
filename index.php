<?php

/**
 * GIT VERSION: 10813
 *
 * Some of the following constants are automatically filled in when
 * the build process is run. If you change them in site/index.php
 * and rerun the build process your changes will be lost
 *
 * APP_PATH   absolute path to the php files of the app
 * DEBUG_MODE flag to enable easier debugging and development
 * CACHE_ID   unique string to bust js/css browser caching for a new build
 * SITE_ID    random site id used for page keys
 */
define('APP_PATH', '');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('WEB_ROOT', '');
define('DEBUG_MODE', true);
define('CACHE_ID', '');
define('SITE_ID', '');
define('JS_HASH', '');
define('CSS_HASH', '');

/* show all warnings in debug mode */
if (DEBUG_MODE) {
    error_reporting(E_ALL | E_STRICT);
}

/* config file location */
define('CONFIG_FILE', APP_PATH.'hm3.rc');

/* don't let anything output content until we are ready */
ob_start();

/* set default TZ */
date_default_timezone_set( 'UTC' );

/* get includes */
require APP_PATH.'lib/framework.php';

/* get configuration */
$config = new Hm_Site_Config_File(CONFIG_FILE);

/* setup ini settings */
if (!$config->get('disable_ini_settings')) {
    require APP_PATH.'lib/ini_set.php';
}

/* process the request */
new Hm_Dispatch($config);

/* log some debug stats about the page */
if (DEBUG_MODE) {
    Hm_Debug::load_page_stats();
    Hm_Debug::show();
}
