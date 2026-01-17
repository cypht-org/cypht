<?php

/* all the things */
error_reporting(E_ALL);

/* debug mode has to be set to something or include files will die() */
if (!defined('DEBUG_MODE')) {
    // Check if we're running a debug test via environment variable
    $debug_mode = getenv('CYPHT_TEST_DEBUG_MODE') === 'true' ? true : false;
    define('DEBUG_MODE', $debug_mode);
}

/* determine current absolute path used for require statements */
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(dirname(dirname(__FILE__))).'/');
}
if (!defined('VENDOR_PATH')) {
    define('VENDOR_PATH', APP_PATH.'vendor/');
}
if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', '');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', APP_PATH.'config/');
}

/* random id */
if (!defined('SITE_ID')) {
    define('SITE_ID', 'randomid');
}

/* cache id */
if (!defined('CACHE_ID')) {
    define('CACHE_ID', 'asdf');
}

/* load composer autoloader */
require_once APP_PATH.'vendor/autoload.php';

/* get mock objects */
require_once APP_PATH.'tests/phpunit/mocks.php';

/* get the framework */
require APP_PATH.'lib/framework.php';

/* get the stubs */
require APP_PATH.'tests/phpunit/stubs.php';

$mock_config = new Hm_Mock_Config();
$user_config = new Hm_User_Config_File($mock_config);
$session = new Hm_PHP_Session($mock_config, 'Hm_Auth_DB');
Hm_Server_Wrapper::init($user_config, $session);
Hm_Tags_Wrapper::init($user_config, $session);

$environment = Hm_Environment::getInstance();
// Load test environment configuration from .env.test
// Create this file by copying .env.test.example: cp .env.test.example .env.test
$environment->load('.env.test');
/* set the default since and per_source values */
$environment->define_default_constants($mock_config);

/* Load modules */
if (file_exists(APP_PATH.'modules/core/modules.php')) {
    require_once APP_PATH.'modules/core/modules.php';
}
if (file_exists(APP_PATH.'modules/saved_searches/modules.php')) {
    require_once APP_PATH.'modules/saved_searches/modules.php';
}