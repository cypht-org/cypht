<?php

/**
 * Spam report target interface
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

interface Hm_Spam_Report_Target_Interface {
    public function id();
    public function label();
    public function capabilities();
    public function requirements();
    public function is_available(Hm_Spam_Report $report, $user_config);
    public function build_payload(Hm_Spam_Report $report, array $user_input = array());
    public function deliver($payload, $context = null);
}
