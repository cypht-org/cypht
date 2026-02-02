<?php

/**
 * Registry for spam report targets
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report_Targets_Registry {
    private $targets = array();

    public function register_target($target) {
        if ($target instanceof Hm_Spam_Report_Target_Interface) {
            $this->targets[$target->id()] = $target;
        }
    }

    public function all_targets() {
        return array_values($this->targets);
    }

    public function get($target_id) {
        if (array_key_exists($target_id, $this->targets)) {
            return $this->targets[$target_id];
        }
        return false;
    }
}
