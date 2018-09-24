<?php

/**
 * Idle timer modules
 * @package modules
 * @subpackage idletimer
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage idletimer/handler
 */
class Hm_Handler_idle_time_check extends Hm_Handler_Module {
    public function process() {
        $logout = false;
        if ($this->session->loaded) {
            $this->session->set('idletime_start', time());
        }
        $start = $this->session->get('idletime_start', 0);
        if (!$start) {
            $logout = true;
        }
        else {
            $max = $this->user_config->get('idle_time', 0)*60*60;
            if ($max && (time() - $start) > $max) {
                $logout = true;
            }
        }
        if ($logout) {
            Hm_Debug::add('IDLETIMER: timer exceeded, logged out');
            $this->session->destroy($this->request);
        }
        else {
            $this->session->set('idletime_start', time());
        }
    }
}

/**
 * @subpackage idletimer/handler
 */
class Hm_Handler_process_idle_time extends Hm_Handler_Module {
    public function process() {
        $idle_time = 0;
        if (array_key_exists('idle_time', $this->request->post)) {
            $idle_time = $this->request->post['idle_time']/60;
        }
        $max = $this->user_config->get('idle_time', 1)*60;
        if ($max && $idle_time >= $max) {
            Hm_Debug::add('IDLETIMER: Logged out after idle period');
            $this->session->destroy($this->request);
        }
    }
}

/**
 * @subpackage idletimer/handler
 */
class Hm_Handler_process_idle_time_setting extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'idle_time'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if (in_array($form['idle_time'], array(0, 1, 2, 3, 24), true)) {
                $new_settings['idle_time'] = $form['idle_time'];
            }
            else {
                $settings['idle_time'] = $this->user_config->get('idle_time', false);
            }
        }
        else {
            $settings['idle_time'] = $this->user_config->get('idle_time', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * @subpackage idletimer/output
 */
class Hm_Output_idle_time_setting extends Hm_Output_Module {
    protected function output() {
        $options = array(
            1 => '1 Hour',
            2 => '2 Hours',
            3 => '3 Hours',
            24 => '1 Day',
            0 => 'Forever'
        );
        $settings = $this->get('user_settings', array());

        if (array_key_exists('idle_time', $settings)) {
            $idle_time = $settings['idle_time'];
        }
        else {
            $idle_time = 1;
        }
        $res = '<tr class="general_setting"><td><label for="idle_time">'.$this->trans('Allowed idle time until logout').'</label></td>'.
            '<td><select id="idle_time" name="idle_time">';
        foreach ($options as $val => $label) {
            $res .= '<option ';
            if ($idle_time == $val) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$val.'">'.$this->trans($label).'</option>';
        }
        $res .= '</select></td></tr>';
        return $res;
    }
}

