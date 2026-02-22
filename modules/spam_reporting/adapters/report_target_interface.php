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
    public function platform_id();
    public function capabilities();
    public function requirements();
    /** @return array<string, array{type: string, label: string, required: bool}> field name => schema */
    public function get_configuration_schema();
    public function is_available(Hm_Spam_Report $report, $user_config, array $instance_config = array());
    public function build_payload(Hm_Spam_Report $report, array $user_input = array(), array $instance_config = array());
    public function deliver($payload, $context = null);
}
