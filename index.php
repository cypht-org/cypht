<?php

/* don't let anything output content until we are ready */
//ob_start();

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
require 'module/handler_modules.php';
require 'module/output_modules.php';
require 'module/module_map.php';

/* get configuration */
$config = new Hm_Config_File('/etc/hastymail2/hastymail2.rc');

/* process request input */
$router = new Hm_Router();
$response_data = $router->process_request($config);

/* format response content */
$formatter = new $response_data['router_format_name']();
$response_str = $formatter->format_content($response_data);

/* output response */
$renderer = new Hm_Output_HTTP();
$renderer->send_response($response_str, $response_data);

/* log execution time to the error log */
error_log(sprintf("Execution Time: %f", (microtime(true) - $start_time)));
error_log(sprintf("Peak Memory: %s", memory_get_peak_usage()));

//Hm_Debug::show();

?>
