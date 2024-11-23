<?php

/**
 * Tags modules
 * @package modules
 * @subpackage tags
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/tags/functions.php';
require_once APP_PATH . 'modules/tags/hm-tags.php';

require_once APP_PATH . 'modules/imap/hm-imap.php';
require_once APP_PATH . 'modules/imap/functions.php';

require_once APP_PATH . 'modules/tags/handler_modules.php';
require_once APP_PATH . 'modules/tags/output_modules.php';
