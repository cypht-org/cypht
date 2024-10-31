<?php

use Services\Core\Hm_Container;
use Symfony\Component\ErrorHandler\ErrorHandler;

define('APP_PATH', dirname(__DIR__).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('CONFIG_PATH', APP_PATH.'config/');
define('WEB_ROOT', '');
define('ASSETS_THEMES_ROOT', '');
define('DEBUG_MODE', true);
define('CACHE_ID', '');
define('SITE_ID', '');
define('JS_HASH', '');
define('CSS_HASH', '');

/* show all warnings in debug mode */
if (DEBUG_MODE) {
    error_reporting(E_ALL);
}

/* don't let anything output content until we are ready */
ob_start();

require VENDOR_PATH.'autoload.php';
/* get includes */
require APP_PATH.'lib/framework.php';
$environment = Hm_Environment::getInstance();
$environment->load();

/* get configuration */
$config = new Hm_Site_Config_File();
/* set default TZ */
date_default_timezone_set($config->get('default_setting_timezone', 'UTC'));
/* set the default since and per_source values */
$environment->define_default_constants($config);

/* setup ini settings */
if (!$config->get('disable_ini_settings')) {
    require APP_PATH.'lib/ini_set.php';
}

$containerBuilder = Hm_Container::getContainer();
// Activer le gestionnaire d'erreurs
ErrorHandler::register();

return [$containerBuilder,$config];
