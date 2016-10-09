<?php

/**
 * @package modules
 * @subpackage dynamic_login
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/dynamic_login/hm-discover-service.php';

/**
 * Process a potential login attempt
 * @subpackage dynamic_login/handler
 */
class Hm_Handler_process_dynamic_login extends Hm_Handler_login {
    /**
     * Perform a new login if the form was submitted, otherwise check for and continue a session if it exists
     */
    public function process() {
        if ($this->config->get('auth_type') != 'dynamic') {
            return parent::process();
        }
        $this->out('dynamic_login', true);
        list($success, $form) = $this->process_form(array('username', 'password', 'email_provider'));
        if ($success) {
            $discover = new Hm_Discover_Services($form['username'], $form['email_provider']);
            $details = $discover->get_host_details();
            $auth_details = array();
            if (array_key_exists('server', $details)) {
                if ($this->module_is_supported('imap') && $details['type'] == 'imap') {
                    $this->config->set('imap_auth_server', $details['server']);
                    $this->config->set('imap_auth_port', $details['port']);
                    $this->config->set('imap_auth_tls', $details['tls']);
                    $this->session->auth_class = 'Hm_Auth_IMAP';
                    $this->session->site_config = $this->config;
                    Hm_Debug::add('Dynamic login override, using Hm_Auth_IMAP');
                    $auth_details = $details;
                }
                elseif ($this->module_is_supported('pop3') && $details['type'] == 'pop3') {
                    $this->config->set('pop3_auth_server', $details['server']);
                    $this->config->set('pop3_auth_port', $details['port']);
                    $this->config->set('pop3_auth_tls', $details['tls']);
                    $this->session->auth_class = 'Hm_Auth_POP3';
                    $this->session->site_config = $this->config;
                    Hm_Debug::add('Dynamic login override, using Hm_Auth_POP3');
                    $auth_details = $details;
                }
            }
            $this->session->check($this->request, rtrim($form['username']), $form['password']);
            $this->session->set('username', rtrim($form['username']));
        }
        else {
            $this->session->check($this->request);
        }
        if ($this->session->is_active()) {
            if ($this->session->loaded && array_key_exists('server', $auth_details)) {
                $auth_details['username'] = $form['username'];
                $auth_details['password'] = $form['password'];
                if ($auth_details['type'] == 'imap') {
                    $this->session->set('imap_auth_server_settings', $auth_details);
                }
                elseif ($auth_details['type'] == 'pop3') {
                    $this->session->set('pop3_auth_server_settings', $auth_details);
                }
                if (array_key_exists('smtp', $auth_details) && count($auth_details['smtp']) > 0) {
                    $this->config->set('default_smtp_server', $auth_details['smtp']['server']);
                    $this->config->set('default_smtp_port', $auth_details['smtp']['port']);
                    $this->config->set('default_smtp_tls', $auth_details['smtp']['tls']);
                    $this->config->set('default_smtp_name', $auth_details['name']);
                }
            }
            Hm_Page_Cache::load($this->session);
            $this->out('changed_settings', $this->session->get('changed_settings', array()), false);
        }
        Hm_Request_Key::load($this->session, $this->request, $this->session->loaded);
        $this->validate_method();
        $this->process_key();
        if (!$this->config->get('disable_origin_check', false)) {
            $this->validate_origin();
        }
    }
}

/**
 * Outputs the login or logout form
 * @subpackage dynamic_login/output
 */
class Hm_Output_dynamic_login extends Hm_Output_login {
    /**
     * Looks at the current login state and outputs the correct form
     */
    protected function output() {
        if (!$this->get('dynamic_login')) {
            return parent::output();
        }
        if (!$this->get('router_login_state')) {
            return '<h1 class="title">'.$this->html_safe($this->get('router_app_name', '')).'</h1>'.
                ' <input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />'.
                ' <label class="screen_reader" for="username">'.$this->trans('E-mail').'</label>'.
                '<input autofocus required type="text" placeholder="'.$this->trans('Username').'" id="username" name="username" value="">'.
                ' <label class="screen_reader" for="password">'.$this->trans('Password').'</label>'.
                '<input required type="password" id="password" placeholder="'.$this->trans('Password').'" name="password">'.
                '<select class="dynamic_service_select" required name="email_provider"><option value="">'.
                $this->trans('E-mail Provider').'</option>'.Nux_Quick_Services::option_list(false, $this).
                '<option value="other">'.$this->trans('Other').'</option></select><br />'.
                ' <input type="submit" value="'.$this->trans('Login').'" />';
        }
        else {
            $settings = $this->get('changed_settings', array());
            return '<input type="hidden" id="unsaved_changes" value="'.
                (!empty($settings) ? '1' : '0').'" />'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<div class="confirm_logout"><div class="confirm_text">'.
                $this->trans('Unsaved changes will be lost! Re-neter your password to save and exit.').' &nbsp;'.
                '<a href="?page=save">'.$this->trans('More info').'</a></div>'.
                '<label class="screen_reader" for="logout_password">'.$this->trans('Password').'</label>'.
                '<input id="logout_password" name="password" class="save_settings_password" type="password" placeholder="'.$this->trans('Password').'" />'.
                '<input class="save_settings" type="submit" name="save_and_logout" value="'.$this->trans('Save and Logout').'" />'.
                '<input class="save_settings" id="logout_without_saving" type="submit" name="logout" value="'.$this->trans('Just Logout').'" />'.
                '<input class="cancel_logout save_settings" type="button" value="'.$this->trans('Cancel').'" />'.
                '</div>';
        }
    }
}

