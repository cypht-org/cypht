<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Handler_process_create_account extends Hm_Handler_Module {
    public function process($data) {
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
        return $data;
    }
}

class Hm_Output_create_account_link extends Hm_Output_Module {
    protected function output($input, $format) {
        if (!$input['router_login_state']) {
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
        if ($input['router_login_state']) {
            page_redirect('?page=home');
        }

        if (true || array_key_exists('internal_users', $input) && $input['internal_users']) {
            return '<div class="create_user">'.
                '<h1 class="title">Create Account</h1>'.
                '<form method="POST" autocomplete="off" >'.
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

?>
