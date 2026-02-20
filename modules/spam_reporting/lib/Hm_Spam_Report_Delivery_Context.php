<?php

/**
 * Delivery context for report targets
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report_Delivery_Context {
    public $site_config;
    public $user_config;
    public $session;
    /** @var array User-provided instance configuration; empty when not using user config */
    public $instance_config = array();

    public function __construct($site_config, $user_config, $session) {
        $this->site_config = $site_config;
        $this->user_config = $user_config;
        $this->session = $session;
    }

    /** @return array */
    public function get_instance_config() {
        return is_array($this->instance_config) ? $this->instance_config : array();
    }
}
