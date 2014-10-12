<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Handler_process_create_account extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('username', 'password', 'create_hm_user'));
        if ($success) {
            if ($this->session->internal_users) {
                $this->session->create($this->request, $form['username'], $form['password']);
            }
        }
        return $data;
    }
}

class Hm_Output_no_login extends Hm_Output_Module {
    protected function output($input, $format) {
        return '';
    }
}

class Hm_Output_create_form extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($input['router_login_state']) {
            page_redirect('?page=home');
        }

        return 'HERE';
            //if (array_key_exists('internal_users', $input) && $input['internal_users'] && $input['router_page_name'] == 'home') {
                //$res .= ' <input type="submit" name="create_hm_user" value="Create" />';
            //}
    }
}

?>
