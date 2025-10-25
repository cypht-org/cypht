<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/imap/handler_modules.php';
require_once APP_PATH.'modules/imap/output_modules.php';
require_once APP_PATH.'modules/imap/functions.php';
require_once APP_PATH.'modules/imap/hm-imap.php';
require_once APP_PATH.'modules/imap/hm-jmap.php';
require_once APP_PATH.'modules/imap/spam_report_config.php';
require_once APP_PATH.'modules/imap/spam_report_services.php';
require_once APP_PATH.'modules/imap/spam_report_utils.php';
require_once APP_PATH.'modules/imap/spam_service_manager.php';
require_once APP_PATH.'modules/imap/sieve_client_factory.php';
