<?php

/**
 * @package modules
 * @subpackage desktop_notifications
 */

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Output_push_js_include extends Hm_Output_Module {
    protected function output() {
        if (DEBUG_MODE) {
            return '<script type="text/javascript" src="third_party/push.min.js"></script>';
        }
    }
}

