<?php

/**
 * Account modules
 * @package modules
 * @subpackage account
 */
if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage account/handler
 */
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

/**
 * @subpackage account/handler
 */
class Hm_Handler_process_create_account extends Hm_Handler_Module {
    public function process() {
        if ($this->session->is_admin()) {
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
}

/**
 * @subpackage account/handler
 */
class Hm_Handler_check_internal_users extends Hm_Handler_Module {
    public function process() {
        $this->out('is_admin', $this->session->is_admin());
        $this->out('internal_users', $this->session->internal_users);
    }
}

/**
 * @subpackage account/output
 */
class Hm_Output_create_account_link extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('is_admin', false)) {
            $res = '';
        }
        else {
            $res = '<li class="menu_create_account"><a class="unread_link" href="?page=create_account">'.
                '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$plus).'" alt="" '.
                'width="16" height="16" /> '.$this->trans('Create Account').'</a></li>';
        }
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage account/output
 */
class Hm_Output_create_form extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('internal_users') || !$this->get('is_admin', false)) {
            Hm_Dispatch::page_redirect('?page=home');
        }
        return '<div class="content_title">'.$this->trans('Create Account').'</div>'.
            '<div class="create_user">'.
            '<form method="POST" autocomplete="off" >'.
            '<input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />'.
            '<input style="display:none" type="text" name="fake_username" />'.
            '<input style="display:none" type="password" name="fake_password" />'.
            ' <input required type="text" placeholder="'.$this->trans('Username').'" name="create_username" value="">'.
            ' <input type="password" required placeholder="'.$this->trans('Password').'" name="create_password">'.
            ' <input type="password" required placeholder="'.$this->trans('Password Again').'" name="create_password_again">'.
            ' <input type="submit" name="create_hm_user" value="'.$this->trans('Create').'" />'.
            '</form></div>';
    }
}

/**
 * @subpackage account/output
 */
class Hm_Output_change_password extends Hm_Output_Module {
    protected function output() {
        $res = '';
        if ($this->get('internal_users')) {
            $res .= '<tr class="general_setting"><td><label for="new_pass1">'.$this->trans('Change password').
                '</label></td><td><input type="password" id="new_pass1" name="new_pass1" placeholder="'.$this->trans('New password').'" />'.
                ' <input type="password" name="new_pass2" placeholder="'.$this->trans('New password again').'" /></td></tr>';
        }
        return $res;
    }
}

?>
