<?php

/**
 * Carddav contact modules
 * @package modules
 * @subpackage carddav_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/carddav_contacts/hm-carddav.php';

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_load_carddav_contacts extends Hm_Handler_Module {
    public function process() {

        $contacts = $this->get('contact_store');
        $auths = $this->user_config->get('carddav_contacts_auth_setting', array());
        $details = get_ini($this->config, 'carddav.ini', true);

        foreach ($details as $name => $vals) {
            if (!array_key_exists($name, $auths)) {
                continue;
            }
            $pass = '';
            if (array_key_exists('pass', $auths[$name])) {
                $pass = $auths[$name]['pass'];
            }
            $carddav = new Hm_Carddav($name, $vals['server'], $auths[$name]['user'], $pass);
            $contacts->import($carddav->addresses);
            $this->append('contact_sources', 'carddav');
        }
        $this->out('contact_store', $contacts, false);
    }
}


/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_load_carddav_settings extends Hm_Handler_Module {
    public function process() {
        $this->out('carddav_settings', get_ini($this->config, 'carddav.ini', true));
        $this->out('carddav_auth', $this->user_config->get('carddav_contacts_auth_setting', array()));
    }
}

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_process_carddav_auth_settings extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('save_settings', $this->request->post)) {
            return;
        }
        $settings = $this->user_config->get('carddav_contacts_auth_setting', array());
        $users = array();
        $passwords = array();
        $results = $settings; 
        if (array_key_exists('carddav_usernames', $this->request->post)) {
            $users = $this->request->post['carddav_usernames'];
        }
        if (array_key_exists('carddav_passwords', $this->request->post)) {
            $passwords = $this->request->post['carddav_passwords'];
        }
        foreach ($settings as $name => $vals) {
            if (array_key_exists($name, $users)) {
                $results[$name]['user'] = $users[$name];
            }
            if (array_key_exists($name, $passwords)) {
                $results[$name]['pass'] = $passwords[$name];
            }
        }
        if (count($results) > 0) {
            $new_settings = $this->get('new_user_settings');
            $new_settings['carddav_contacts_auth_setting'] = $results;
            $this->out('new_user_settings', $new_settings, false);
        }
    }
}

/**
 * @subpackage carddav_contacts/output
 */
class Hm_Output_carddav_auth_settings extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('carddav_settings', array());
        $auths = $this->get('carddav_auth', array());
        if (count($settings) == 0) {
            return;
        }
        $res = '<tr><td data-target=".carddav_settings" colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$people.'" width="16" height="16" />'.
            $this->trans('CardDav Addressbooks').'</td></tr>';
        foreach ($settings as $name => $vals) {
            $user = '';
            $pass = false;
            if (array_key_exists($name, $auths)) {
                $user = $auths[$name]['user'];
                if (array_key_exists('pass', $auths[$name]) && $auths[$name]['pass']) {
                    $pass = true;
                }
            }
            $res .= '<tr class="carddav_settings"><td>'.$this->html_safe($name).'</td><td>';
            $res .= '<input autocomplete="username" type="text" value="'.$user.'" name="carddav_usernames['.$this->html_safe($name).']" ';
            $res .= 'placeholder="'.$this->trans('Username').'" /> <input type="password" ';
            if ($pass) {
                $res .= 'disabled="disabled" placeholder="'.$this->trans('Password saved').'" ';
                $res .= 'name="carddav_passwords['.$this->html_safe($name).']" /> <input type="button" ';
                $res .= 'value="'.$this->trans('Unlock').'" class="carddav_password_change" /></td></tr>';
            }
            else {
                $res .= 'autocomplete="new-password" placeholder="'.$this->trans('Password').'" ';
                $res .= 'name="carddav_passwords['.$this->html_safe($name).']" /></td></tr>';
            }
        }
        return $res;
    }
}
