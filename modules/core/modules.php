<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

if (!defined('DEBUG_MODE')) { die(); }

define('MAX_PER_SOURCE', 100);
define('DEFAULT_PER_SOURCE', 20);
define('DEFAULT_SINCE', '-1 week');

require APP_PATH.'modules/core/functions.php';
require APP_PATH.'modules/core/message_functions.php';
require APP_PATH.'modules/core/message_list_functions.php';
require APP_PATH.'modules/core/handler_modules.php';
require APP_PATH.'modules/core/output_modules.php';

