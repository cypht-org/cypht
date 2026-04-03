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
define('APP_PATH', dirname(__FILE__).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('CONFIG_PATH', APP_PATH.'config/');
define('WEB_ROOT', '');
define('ASSETS_THEMES_ROOT', '');
define('CACHE_ID', '');
define('SITE_ID', '');
define('JS_HASH', '');
define('CSS_HASH', '');
define('ASSETS_PATH', APP_PATH.'assets/');

/* don't let anything output content until we are ready */
ob_start();

// Only require the autoloader if it exists, usually in cases where Cypht is not being used as a library.
if (file_exists(VENDOR_PATH.'autoload.php')) {
    require VENDOR_PATH.'autoload.php';
}

/* get includes */
require APP_PATH.'lib/framework.php';
$environment = Hm_Environment::getInstance();
$environment->load();

/* initialize glitchtip to capture errors */
$glitchtip_dsn = env('GLITCHTIP_DSN', '');

if ($glitchtip_dsn) {
    \Sentry\init([
        'dsn' => $glitchtip_dsn,
        'traces_sample_rate' => env('GLITCHTIP_TRACES_SAMPLE_RATE', 0.01),
    ]);
}

define('DEBUG_MODE', filter_var(env('ENABLE_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

/* show all warnings in debug mode */
if (DEBUG_MODE) {
    error_reporting(E_ALL);
}

/* get configuration */
if (env('SITE_CONFIG_TYPE') == 'custom') {
    $site_module = basename(env('SITE_MODULE_PATH', ''));
    if (is_readable(APP_PATH. "modules/$site_module/lib.php")) {
        require_once APP_PATH . "modules/$site_module/lib.php";
        $config = new Hm_Custom_Site_Config();
    }
}

if (! isset($config)) {
    $config = new Hm_Site_Config_File();
}

/* set default TZ */
date_default_timezone_set($config->get('default_setting_timezone', 'UTC'));
/* set the default since and per_source values */
$environment->define_default_constants($config);

/* setup ini settings */
if (!$config->get('disable_ini_settings')) {
    require APP_PATH.'lib/ini_set.php';
}

/* log some debug stats about the page */
if (DEBUG_MODE) {
    Hm_Debug::load_page_stats();
}

/* process the request */
$dispatcher = new Hm_Dispatch($config);

if (empty($config)) {
    $config = new Hm_Site_Config_File();
}
