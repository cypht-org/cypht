<?php

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
require 'lib/hm-imap.php';

/* get configuration */
$config = new Hm_Config_File('hm3.rc');

/* process request input */
$router = new Hm_Router();
$response_data = $router->process_request($config);

/* format response content */
$formatter = new $response_data['router_format_name']();
$response_str = $formatter->format_content($response_data);

/* output response */
$renderer = new Hm_Output_HTTP();
$renderer->send_response($response_str, $response_data);

/* debug FTW! */
Hm_Debug::load_page_stats($start_time);
Hm_Debug::show(true);

?>
