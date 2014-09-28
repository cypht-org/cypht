<?php

/* constants */
define('DEBUG_MODE', false);
define('MCRYPT_DATA', true);
define('BLOCK_MODE', MCRYPT_MODE_CBC);
define('CIPHER', MCRYPT_RIJNDAEL_128);
define('RAND_SOURCE', MCRYPT_RAND);
define('MAX_PER_SOURCE', 100);
define('DEFAULT_PER_SOURCE', 20);
define('DEFAULT_SINCE', 'today');

/* compress output if possible */
ini_set("zlib.output_compression", "On");

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
require 'third_party/pbkdf2.php';

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
$session->end();

if (DEBUG_MODE) {
    Hm_Debug::load_page_stats();
    Hm_Debug::show('log');
}
?>
