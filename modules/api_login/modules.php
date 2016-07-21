<?php

/**
 * @package modules
 * @subpackage api_login
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage dynamic_login/handler
 */
class Hm_Handler_process_api_login extends Hm_Handler_login {
    public function process() {
        $api_login = false;
        if (!$this->get('create_username', false)) {
            list($success, $form) = $this->process_form(array('username', 'password'));
            if ($success) {
                $this->session->check($this->request, rtrim($form['username']), $form['password']);
                $this->session->set('username', rtrim($form['username']));
                if (array_key_exists('api_login_key', $this->request->post)) {
                    if ($this->request->post['api_login_key'] == $this->config->get('api_login_key')) {
                        $api_login = true;
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
            $this->process_key();
        }
        else {
            header('Content-Type: application/json');
            echo json_encode(array(
                'hm_id' => $this->session->enc_key,
                'hm_session' => $this->session->session_key
            ));
            exit;
        }
    }
}
