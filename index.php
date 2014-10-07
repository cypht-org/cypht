<?php

/* constants */
define('DEBUG_MODE', false);
define('MAX_PER_SOURCE', 100);
define('DEFAULT_PER_SOURCE', 20);
define('DEFAULT_SINCE', 'today');

/* compress output if possible */
ini_set("zlib.output_compression", "On");

/* don't let anything output content until we are ready */
ob_start();

/* show all warnings in debug mode */
if (DEBUG_MODE) {
    error_reporting(E_ALL | E_STRICT);
}

/* set default TZ */
date_default_timezone_set( 'UTC' );

/* get includes */
require 'lib/modules.php';
require 'lib/config.php';
require 'lib/auth.php';
require 'lib/session.php';
require 'lib/format.php';
require 'lib/router.php';
require 'lib/request.php';
require 'lib/cache.php';
require 'lib/output.php';
require 'lib/crypt.php';
require 'lib/db.php';
require 'lib/servers.php';

/* get configuration */
$config = new Hm_Site_Config_File('hm3.rc');

/* process request input */
$router = new Hm_Router();
list($response_data, $session, $allowed_output) = $router->process_request($config);

/* format response content */
$formatter = new $response_data['router_format_name']();
$response_str = $formatter->format_content($response_data, $allowed_output);

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
