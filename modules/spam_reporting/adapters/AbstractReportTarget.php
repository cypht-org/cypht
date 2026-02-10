<?php

/**
 * Base adapter for spam report targets
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

abstract class Hm_Spam_Report_Target_Abstract implements Hm_Spam_Report_Target_Interface {
    public function configure(array $config) {
    }

    public function platform_id() {
        return '';
    }

    public function capabilities() {
        return array();
    }

    public function requirements() {
        return array();
    }

    /**
     * Schema for user-provided instance configuration. Override in adapters that support it.
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function get_configuration_schema() {
        return array();
    }

    public function is_available(Hm_Spam_Report $report, $user_config, array $instance_config = array()) {
        return true;
    }
}
