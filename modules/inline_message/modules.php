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
        $this->out('inline_message_setting', $this->user_config->get('inline_message_setting', DEFAULT_INLINE_MESSAGE));
        $this->out('inline_message_style', $this->user_config->get('inline_message_style_setting', DEFAULT_INLINE_MESSAGE_STYLE));
    }
}

/**
 * @subpackage inline_message/handler
 */
class Hm_Handler_process_inline_message_style extends Hm_Handler_Module {
    public function process() {
        function inline_message_style_callback($val) {
            if (in_array($val, array('right', 'inline'), true)) {
                return $val;
            }
            return DEFAULT_INLINE_MESSAGE_STYLE;
        }
        process_site_setting('inline_message_style', $this, 'inline_message_style_callback', DEFAULT_INLINE_MESSAGE_STYLE, true);
    }
}

/**
 * @subpackage inline_message/handler
 */
class Hm_Handler_process_inline_message_setting extends Hm_Handler_Module {
    public function process() {
        function inline_message_callback($val) { return $val; }
        process_site_setting('inline_message', $this, 'inline_message_callback', DEFAULT_INLINE_MESSAGE, true);
    }
}


/**
 * @subpackage inline_message/output
 */
class Hm_Output_inline_message_flag extends Hm_Output_Module {
    protected function output() {
        return '<script type="text/javascript" id="inline-msg-state">var inline_msg_style = function() { return "'.
            $this->get('inline_message_style', DEFAULT_INLINE_MESSAGE_STYLE).'";}; var inline_msg = function() { return '.
            ($this->get('inline_message_setting', DEFAULT_INLINE_MESSAGE) && !$this->get('is_mobile', false) ? 'true' : 'false').
            ';};</script>';
    }
}

/**
 * @subpackage inline_message/output
 */
class Hm_Output_inline_message_setting extends Hm_Output_Module {
    protected function output() {
        $inline = DEFAULT_INLINE_MESSAGE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('inline_message', $settings)) {
            $inline = $settings['inline_message'];
        }
        $res = '<tr class="general_setting"><td class="d-block d-md-table-cell"><label for="inline_message">'.$this->trans('Show messages inline').'</label></td><td class="d-block d-md-table-cell"><div class="d-flex align-items-center"><input value="1" type="checkbox" class="form-check-input me-2" name="inline_message" id="inline_message" data-default-value="'.(DEFAULT_INLINE_MESSAGE ? 'true' : 'false') . '"';
        $reset = '';
        if ($inline) {
            $res .= ' checked="checked"';
        }
        if($inline !== DEFAULT_INLINE_MESSAGE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise fs-6 cursor-pointer refresh_list reset_default_value_checkbox"></i></span>';
        }
        $res .= '>'.$reset.'</div></td></tr>';
        return $res;
    }
}

/**
 * @subpackage inline_message/output
 */
class Hm_Output_inline_message_style extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $selected = '';
        $reset = '';
        if (array_key_exists('inline_message_style', $settings)) {
            $selected = $settings['inline_message_style'];
        }
        $res = '<tr class="general_setting"><td class="d-block d-md-table-cell"><label>'.$this->trans('Inline Message Style').'</label></td><td class="d-block d-md-table-cell"><div class="d-flex align-items-center"><select class="form-select form-select-sm w-auto" name="inline_message_style" data-default-value="'.DEFAULT_INLINE_MESSAGE_STYLE.'">';
        $res .= '<option ';
        if ($selected == 'right') {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="right">'.$this->trans('Right').'</option><option ';
        if ($selected == 'inline') {
            $res .= 'selected="selected" ';
        }
        if($selected !== DEFAULT_INLINE_MESSAGE_STYLE) {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_select"></i></span>';
        }
        $res .= 'value="inline">'.$this->trans('Inline').'</option></select>'.$reset.'</div></td></tr>';
        return $res;
    }
}
