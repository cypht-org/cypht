<?php

/**
 * @package modules
 * @subpackage api_login
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage api_login/handler
 */
class Hm_Handler_api_login_step_two extends Hm_Handler_login {
    public function process() {
        list($success, $form) = $this->process_form(array('hm_id', 'hm_session', 'api_login_key'));
        if (!$success) {
            return;
        }
        if ($form['api_login_key'] != $this->config->get('api_login_key')) {
            return;
        }
        list($secure, $path, $domain) = $this->session->set_session_params($this->request);
        Hm_Functions::setcookie('hm_id', stripslashes($form['hm_id']), 0, $path, $domain, $secure, true);
        Hm_Functions::setcookie('hm_session', stripslashes($form['hm_session']), 0, $path, $domain, $secure, true);
        Hm_Dispatch::page_redirect('?page=home');
    }
}

/**
 * @subpackage api_login/handler
 */
class Hm_Handler_process_api_login extends Hm_Handler_login {
    public function process() {
        if (array_key_exists('api_login_key', $this->request->post) &&
            $this->request->post['api_login_key'] == $this->config->get('api_login_key')) {
            $this->validate_request = false;
        }
        parent::process();
        if (!$this->validate_request && $this->session->is_active()) {
            $this->user_config->load(rtrim($this->request->post['username']), $this->request->post['password']);
            $user_data = $this->user_config->dump();
            $this->session->set('user_data', $user_data);
            header('Content-Type: application/json');
            $res = array(
                'hm_id' => $this->session->enc_key,
                'hm_session' => $this->session->session_key
            );
            echo json_encode($res);
            $this->session->end();
            Hm_Debug::load_page_stats();
            Hm_Debug::show();
            Hm_Functions::cease();
        }
    }
}
