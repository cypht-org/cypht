<?php

/**
 * Spam reporting modules
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/spam_reporting/lib/hm-spam-report.php';
require_once APP_PATH . 'modules/spam_reporting/lib/hm-spam-report-payload.php';
require_once APP_PATH . 'modules/spam_reporting/lib/hm-spam-report-delivery-context.php';
require_once APP_PATH . 'modules/spam_reporting/lib/hm-spam-report-result.php';
require_once APP_PATH . 'modules/spam_reporting/functions.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/report_target_interface.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/abstract_report_target.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/email_report_target.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/spamcop_email_report_target.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/abstract_api_report_target.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/abuseipdb_report_target.php';
require_once APP_PATH . 'modules/spam_reporting/spam_reporting_manager.php';

require_once APP_PATH . 'modules/spam_reporting/handler_modules.php';
require_once APP_PATH . 'modules/spam_reporting/output_modules.php';
