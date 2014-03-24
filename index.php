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
require 'lib/hm-imap.php';
require 'lib/hm-pop3.php';

/* get configuration */
$config = new Hm_Site_Config_File('hm3.rc');

/* process request input */
$router = new Hm_Router();
$response_data = $router->process_request($config);

/* format response content */
$formatter = new $response_data['router_format_name']();
$response_str = $formatter->format_content($response_data);

/* output response */
$renderer = new Hm_Output_HTTP();
$renderer->send_response($response_str, $response_data);

/* TODO:
 * smtp
 * - add/del/test on servers page
 * - extend list class
 * - add to tracker
 * 
 * pop3
 * - add to unread(!)
 *
 * display_cache in output mods
 * test removing a module ...
 * plugin/ability to make auth single server imap based
 */
?>
