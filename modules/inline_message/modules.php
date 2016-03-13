<?php

/**
 * inline message modules
 * @package modules
 * @subpackage inline_message
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage inline_message/handler
 */
class Hm_Handler_get_inline_message_setting extends Hm_Handler_Module {
    public function process() {
        $this->out('inline_message_setting', $this->user_config->get('inline_message_setting', false));
    }
}

/**
 * @subpackage inline_message/handler
 */
class Hm_Handler_process_inline_message_setting extends Hm_Handler_Module {
    public function process() {
        function inline_message_callback($val) { return $val; }
        process_site_setting('inline_message', $this, 'inline_message_callback', false, true);
    }
}


/**
 * @subpackage inline_message/output
 */
class Hm_Output_inline_message_flag extends Hm_Output_Module {
    protected function output() {
        return '<script type="text/javascript">var inline_msg = function() { return '.
            ($this->get('inline_message_setting', false) && !$this->get('is_mobile', false) ? 'true' : 'false').
            ';};</script>';
    }
}

/**
 * @subpackage inline_message/output
 */
class Hm_Output_inline_message_setting extends Hm_Output_Module {
    protected function output() {
        $inline = false;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('inline_message', $settings)) {
            $inline = $settings['inline_message'];
        }
        $res = '<tr class="general_setting"><td>'.$this->trans('Show messages inline').'</td><td><input value="1" type="checkbox" name="inline_message"';
        if ($inline) {
            $res .= ' checked="checked"';
        }
        $res .= '></td></tr>';
        return $res;
    }
}

