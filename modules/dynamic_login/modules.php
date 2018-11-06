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
        $login_config = dynamic_login_config($this->config);
        $this->out('dynamic_config', $login_config);
        $this->out('dynamic_login', true);
        list($success, $form) = $this->process_form(array('username', 'password', 'email_provider'));
        if ($success) {
            $discover = new Hm_Discover_Services($form['username'], $login_config, $form['email_provider'], $this->request->server['HTTP_HOST']);
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
                    $this->session->set('auth_class', 'Hm_Auth_IMAP');
                }
                elseif ($this->module_is_supported('pop3') && $details['type'] == 'pop3') {
                    $this->config->set('pop3_auth_server', $details['server']);
                    $this->config->set('pop3_auth_port', $details['port']);
                    $this->config->set('pop3_auth_tls', $details['tls']);
                    $this->session->auth_class = 'Hm_Auth_POP3';
                    $this->session->site_config = $this->config;
                    Hm_Debug::add('Dynamic login override, using Hm_Auth_POP3');
                    $this->session->set('auth_class', 'Hm_Auth_POP3');
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
            else {
                $imap_details = $this->session->get('imap_auth_server_settings', array());
                if (count($imap_details) > 0) {
                    $this->config->set('imap_auth_server', $imap_details['server']);
                    $this->config->set('imap_auth_port', $imap_details['port']);
                    $this->config->set('imap_auth_tls', $imap_details['tls']);
                }
                $pop3_details = $this->session->get('pop3_auth_server_settings', array());
                if (count($pop3_details) > 0) {
                    $this->config->set('pop3_auth_server', $pop3_details['server']);
                    $this->config->set('pop3_auth_port', $pop3_details['port']);
                    $this->config->set('pop3_auth_tls', $pop3_details['tls']);
                }
            }
            $this->session->auth_class = $this->session->get('auth_class');
            $this->out('changed_settings', $this->session->get('changed_settings', array()), false);
        }
        Hm_Request_Key::load($this->session, $this->request, $this->session->loaded);
        $this->validate_method($this->session, $this->request);
        $this->process_key();
        if (!$this->config->get('disable_origin_check', false)) {
            $this->validate_origin($this->session, $this->request, $this->config);
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
        $config = $this->get('dynamic_config', array ());
        if (!$this->get('router_login_state')) {
            $res = '<h1 class="title">'.$this->html_safe($this->get('router_app_name', '')).'</h1>'.
                ' <input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />'.
                ' <label class="screen_reader" for="username">'.$this->trans('E-mail').'</label>'.
                '<input autofocus required type="text" placeholder="'.$this->trans('Username').'" id="username" name="username" value="">'.
                ' <label class="screen_reader" for="password">'.$this->trans('Password').'</label>'.
                '<input required type="password" id="password" placeholder="'.$this->trans('Password').'" name="password">';
            if (array_key_exists('user', $config) && $config['user'] || array_key_exists('host', $config) && $config['host']) {
                $res .= '<input type="hidden" value="other" name="email_provider" />';
            }
            else {
                $res .= '<select class="dynamic_service_select" required name="email_provider"><option value="">'.
                    $this->trans('E-mail Provider').'</option>'.Nux_Quick_Services::option_list(false, $this).
                    '<option value="other">'.$this->trans('Other').'</option></select><br />';
            }
            $res .= ' <input type="submit" value="'.$this->trans('Login').'" />';
            return $res;
        }
        else {
            $settings = $this->get('changed_settings', array());
            return '<input type="hidden" id="unsaved_changes" value="'.
                (!empty($settings) ? '1' : '0').'" />'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<div class="confirm_logout"><div class="confirm_text">'.
                $this->trans('Unsaved changes will be lost! Re-enter your password to save and exit.').' &nbsp;'.
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

/**
 * @subpackage dynamic_login/functions
 */
if (!hm_exists('dynamic_login_config')) {
function dynamic_login_config($config) {
    $settings = array(
        'host' => false,
        'user' => false,
        'host_pre' => '',
        'mail_pre' => '',
        'smtp_pre' => ''
    );
    $res = get_ini($config, 'dynamic_login.ini');
    if (array_key_exists('dynamic_host', $res) && $res['dynamic_host']) {
        $settings['host'] = $res['dynamic_host'];
    }
    if (array_key_exists('dynamic_user', $res) && $res['dynamic_user']) {
        $settings['user'] = $res['dynamic_user'];
    }
    if (array_key_exists('dynamic_host_subdomain', $res) && $res['dynamic_host_subdomain']) {
        $settings['host_pre'] = $res['dynamic_host_subdomain'];
    }
    if (array_key_exists('dynamic_mail_subdomain', $res) && $res['dynamic_mail_subdomain']) {
        $settings['mail_pre'] = $res['dynamic_mail_subdomain'];
    }
    if (array_key_exists('dynamic_smtp_subdomain', $res) && $res['dynamic_smtp_subdomain']) {
        $settings['smtp_pre'] = $res['dynamic_smtp_subdomain'];
    }
    return $settings;
}}
