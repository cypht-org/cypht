<?php

/* constants */
define('DEBUG_MODE', false);
define('MCRYPT_DATA', true);
define('BLOCK_MODE', MCRYPT_MODE_CBC);
define('CIPHER', MCRYPT_RIJNDAEL_128);
define('RAND_SOURCE', MCRYPT_RAND);

/* don't let anything output content until we are ready */
ob_start();

/* show all warnings */
error_reporting(E_ALL | E_STRICT);

/* set default TZ */
date_default_timezone_set( 'UTC' );

/* start a simple page performance timer */
$start_time = microtime(true);

/* get includes */
require 'lib/framework.php';
require 'lib/session.php';
require 'lib/pbkdf2.php';

/* get configuration */
$config = new Hm_Site_Config_File('hm3.rc');

/* process request input */
$router = new Hm_Router();
list($response_data, $session) = $router->process_request($config);

/* format response content */
$formatter = new $response_data['router_format_name']();
$response_str = $formatter->format_content($response_data);

/* output response */
$renderer = new Hm_Output_HTTP();
$renderer->send_response($response_str, $response_data);

/* save any cached stuff */
Hm_Page_Cache::save($session);

/* close down the session */
if ($session->is_active()) {
    $session->end();
}

/* log some debug info if not in debug mode */
if (!DEBUG_MODE) {
    Hm_Debug::load_page_stats();
    Hm_Debug::show('log');
}
?>
