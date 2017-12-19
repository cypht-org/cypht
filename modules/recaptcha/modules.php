<?php

/**
 * recaptcha modules
 * @package modules
 * @subpackage recaptcha
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage recaptcha/handler
 */
class Hm_Handler_process_recaptcha extends Hm_Handler_Module {
    public function process() {
        $rconf = recaptcha_config($this->config);
        if (!is_array($rconf) || count($rconf) == 0) {
            $this->out('recaptcha_config', array('site_key' => ''));
            Hm_Debug::add('Recaptcha module activated, but not configured');
            return;
        }
        $this->out('recaptcha_config', $rconf);
        list($success, $form) = $this->process_form(array('username', 'password'));
        if (!$success) {
            return;
        }
        if (!array_key_exists('g-recaptcha-response', $this->request->post)) {
            $this->request->post = array();
            Hm_Msgs::add('ERRRecaptcha failed');
            return;
        }
        if (!check_recaptcha($rconf['secret'], $this->request->post['g-recaptcha-response'],
            $this->request->server['REMOTE_ADDR'])) {
            $this->request->post = array();
            Hm_Msgs::add('ERRRecaptcha failed');
            return;
        }
    }
}

/**
 * @subpackage recaptcha/output
 */
class Hm_Output_recaptcha_script extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('router_login_state')) {
            return "<script src='https://www.google.com/recaptcha/api.js'></script>";
        }
    }
}

/**
 * @subpackage recaptcha/output
 */
class Hm_Output_recaptcha_form extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('router_login_state')) {
            $rconf = $this->get('recaptcha_config');
            return '<div class="g-recaptcha" data-sitekey="'.$this->html_safe($rconf['site_key']).'"></div>';
        }
    }
}

/**
 * @subpackage recaptcha/functions
 */
if (!hm_exists('recaptcha_config')) {
function recaptcha_config($config) {
    return get_ini($config, 'recaptcha.ini');
}}

/**
 * @subpackage recaptcha/functions
 */
if (!hm_exists('check_recaptcha')) {
function check_recaptcha($secret, $response, $ip) {
    $api = new Hm_API_Curl();
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $post = array('secret' => $secret, 'response' => $response, 'remoteip' => $ip);
    $res = $api->command($url, array(), $post);
    if (is_array($res) && array_key_exists('success', $res) && $res['success']) {
        return true;
    }
    return false;
}}

