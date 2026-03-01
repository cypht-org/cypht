<?php

/**
 * Delivery result container
 * @package modules
 * @subpackage spam_reporting
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Spam_Report_Result {
    public $ok;
    public $message;
    public $details;

    public function __construct($ok, $message = '', array $details = array()) {
        $this->ok = (bool) $ok;
        $this->message = $message;
        $this->details = $details;
    }
}
