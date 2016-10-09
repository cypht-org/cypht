<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

define('MAX_PER_SOURCE', 1000);
define('DEFAULT_PER_SOURCE', 20);
define('DEFAULT_SINCE', '-1 week');
define('DEFAULT_SEARCH_FLD', 'TEXT');

require_once APP_PATH.'modules/core/functions.php';
require APP_PATH.'modules/core/message_functions.php';
require APP_PATH.'modules/core/message_list_functions.php';
require APP_PATH.'modules/core/handler_modules.php';
require APP_PATH.'modules/core/output_modules.php';

