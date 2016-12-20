<?php

/**
 * @package modules
 * @subpackage api_login
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage dynamic_login/handler
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
 * @subpackage dynamic_login/handler
 */
class Hm_Handler_process_api_login extends Hm_Handler_login {
    public function process() {
        $api_login = false;
        if (!$this->get('create_username', false)) {
            list($success, $form) = $this->process_form(array('username', 'password'));
            if ($success) {
                $this->session->check($this->request, rtrim($form['username']), $form['password'], false);
                $this->session->set('username', rtrim($form['username']));
                if (array_key_exists('api_login_key', $this->request->post)) {
                    if ($this->request->post['api_login_key'] == $this->config->get('api_login_key')) {
                        $api_login = true;
                        $this->user_config->load(rtrim($form['username']), $form['password']);
                        $user_data = $this->user_config->dump();
                        $this->session->set('user_data', $user_data);
                    }
                }
            }
            else {
                $this->session->check($this->request);
            }
            if ($this->session->is_active()) {
                Hm_Page_Cache::load($this->session);
                $this->out('changed_settings', $this->session->get('changed_settings', array()), false);
            }
        }
        if (!$api_login) {
            Hm_Request_Key::load($this->session, $this->request, $this->session->loaded);
            $this->validate_method();
            $this->process_key();
            if (!$this->config->get('disable_origin_check', false)) {
                $this->validate_origin();
            }
        }
        else {
            header('Content-Type: application/json');
            $res = array(
                'hm_id' => $this->session->enc_key,
                'hm_session' => $this->session->session_key
            );
            echo json_encode($res);
            $this->session->end();
            Hm_Debug::load_page_stats();
            Hm_Debug::show('log');
            Hm_Functions::cease();
        }
    }
}
