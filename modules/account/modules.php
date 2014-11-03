<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Handler_process_change_password extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('new_pass1', 'new_pass2'));
        if ($success) {
            if ($this->session->internal_users) {
                if ($form['new_pass1'] && $form['new_pass2']) {
                    if ($form['new_pass1'] != $form['new_pass2']) {
                        Hm_Msgs::add("ERRNew passwords don't match");
                    }
                    else {
                        $user = $this->session->get('username', false);
                        if ($this->session->change_pass($user, $form['new_pass1'])) {
                            $this->out('new_password', $form['new_pass1']);
                        }
                    }
                }
            }
        }
    }
}

class Hm_Handler_process_create_account extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('create_username', 'create_password', 'create_password_again'));
        if ($success) {
            if ($form['create_password'] == $form['create_password_again']) {
                if ($this->session->internal_users) {
                    $this->session->create($this->request, $form['create_username'], $form['create_password']);
                }
            }
            else {
                Hm_Msgs::add('ERRPasswords did not match');
            }
        }
    }
}

class Hm_Handler_check_internal_users extends Hm_Handler_Module {
    public function process() {
        $this->out('internal_users', $this->session->internal_users);
    }
}

class Hm_Output_create_account_link extends Hm_Output_Module {
    protected function output($input, $format) {
        if (!$this->get('router_login_state') && $this->get('internal_users')) {
            return '<a class="create_account_link" href="?page=create_account">Create</a>';
        }
    }
}

class Hm_Output_no_login extends Hm_Output_Module {
    protected function output($input, $format) {
        return '';
    }
}

class Hm_Output_create_form extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($this->get('router_login_state')) {
            page_redirect('?page=home');
        }
        if ($this->get('internal_users')) {
            return '<div class="create_user">'.
                '<h1 class="title">Create Account</h1>'.
                '<form method="POST" autocomplete="off" >'.
                '<input type="hidden" name="hm_nonce" value="'.Hm_Nonce::site_key().'" />'.
                '<!-- fake fields are a workaround for chrome autofill getting the wrong fields -->'.
                '<input style="display:none" type="text" name="fake_username" />'.
                '<input style="display:none" type="password" name="fake_password" />'.
                ' <input required type="text" placeholder="'.$this->trans('Username').'" name="create_username" value="">'.
                ' <input type="password" required placeholder="'.$this->trans('Password').'" name="create_password">'.
                ' <input type="password" required placeholder="'.$this->trans('Password Again').'" name="create_password_again">'.
                ' <input type="submit" name="create_hm_user" value="Create" />'.
                '</form></div>'.
                '<a class="create_account_link" href="?page=home">Login</a>';
        }
    }
}

class Hm_Output_change_password extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ($this->get('internal_users')) {
            $res .= '<tr class="general_setting"><td>Change password</td><td><input type="password" name="new_pass1" placeholder="New password" />'.
                ' <input type="password" name="new_pass2" placeholder="New password again" /></td></tr>';
        }
        return $res;
    }
}


?>
