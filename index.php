<?php

/* show all warnings */
error_reporting(E_ALL | E_STRICT);

/* start a simple page performance timer */
$start_time = microtime(true);

/* don't let anything output content until we are ready */
ob_start();

/* get includes */
require_once('framework.php');
require_once('session.php');
require_once('output.php');
require_once('handlers.php');

/* get configuration */
$config = new Hm_Config_File('/etc/hastymail2/hastymail2.rc');

/* process request input */
$router = new Hm_Router();
$response = $router->process_request($config);

/* format response content */
$formatter = new $response['format']();
$response = $formatter->format_content($response);

/* output response */
$renderer = new Hm_Output_HTTP();
$renderer->send_response($response);

/* log execution time to the error log */
Hm_Debug::show();

error_log(sprintf("Execution Time: %f", (microtime(true) - $start_time)));
error_log(sprintf("Peak Memory: %s", memory_get_peak_usage()));

?>
