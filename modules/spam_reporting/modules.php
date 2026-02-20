<?php

/**
 * Spam reporting modules
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/spam_reporting/lib/Hm_Spam_Report.php';
require_once APP_PATH . 'modules/spam_reporting/lib/Hm_Spam_Report_Payload.php';
require_once APP_PATH . 'modules/spam_reporting/lib/Hm_Spam_Report_Delivery_Context.php';
require_once APP_PATH . 'modules/spam_reporting/lib/Hm_Spam_Report_Result.php';
require_once APP_PATH . 'modules/spam_reporting/functions.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/ReportTargetInterface.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/AbstractReportTarget.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/TargetsRegistry.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/EmailReportTarget.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/SpamCopEmailReportTarget.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/AbstractApiReportTarget.php';
require_once APP_PATH . 'modules/spam_reporting/adapters/AbuseIPDBReportTarget.php';
require_once APP_PATH . 'modules/spam_reporting/SpamReportingManager.php';

require_once APP_PATH . 'modules/spam_reporting/handler_modules.php';
require_once APP_PATH . 'modules/spam_reporting/output_modules.php';
