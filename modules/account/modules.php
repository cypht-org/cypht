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
        if (!$this->session->internal_users) {
            return;
        }

        list($success, $form) = $this->process_form(array('new_pass1', 'new_pass2', 'old_pass', 'change_password'));
        if (!$success) {
            return;
        }
        if ($form['new_pass1'] !== $form['new_pass2']) {
            Hm_Msgs::add("ERRNew passwords don't not match"); 
            return;
        }
        $user = $this->session->get('username', false);
        if (!$this->session->auth($user, $form['old_pass'])) {
            Hm_Msgs::add("ERRCurrent password is incorrect");
            return;
        }
        $user_config = load_user_config_object($this->config);
        if ($this->session->change_pass($user, $form['new_pass1'])) {
            Hm_Msgs::add("Password changed");
            return;
        }
        Hm_Msgs::add("ERRAn error Occurred");
        $user_config->load($user, $form['old_pass']);
        $user_config->save($user, $form['new_pass1']);
    }
}

/**
 * @subpackage account/handler
 */
class Hm_Handler_process_delete_account extends Hm_Handler_Module {
    public function process() {
        if (!$this->session->is_admin()) {
            return;
        }
        if (!$this->session->internal_users) {
            return;
        }
        list($success, $form) = $this->process_form(array('delete_username'));
        if (!$success) {
            return;
        }
        $dbh = Hm_DB::connect($this->config);
        if (Hm_DB::execute($dbh, 'delete from hm_user where username=?', array($form['delete_username']))) {
            Hm_Msgs::add('User account deleted');
        }
        else {
            Hm_Msgs::add('ERRAn error occurred deleting the account');
        }
    }
}

/**
 * @subpackage account/handler
 */
class Hm_Handler_account_list extends Hm_Handler_Module {
    public function process() {
        if (!$this->session->is_admin()) {
            return;
        }
        if (!$this->session->internal_users) {
            return;
        }
        $dbh = Hm_DB::connect($this->config);
        $this->out('user_list', Hm_DB::execute($dbh, 'select username from hm_user', array(), false, true));
    }
}

/**
 * @subpackage account/handler
 */
class Hm_Handler_process_create_account extends Hm_Handler_Module {
    public function process() {
        if (!$this->session->is_admin()) {
            return;
        }
        if (!$this->session->internal_users) {
            return;
        }
        list($success, $form) = $this->process_form(array('create_username', 'create_password', 'create_password_again'));
        if (!$success) {
            return;
        }
        if ($form['create_password'] != $form['create_password_again']) {
            Hm_Msgs::add('ERRPasswords did not match');
            return;
        }
        $res = $this->session->create($form['create_username'], $form['create_password']);
        if ($res === 1) {
            Hm_Msgs::add("ERRThat username is already in use");
        }
        elseif ($res === 2) {
            Hm_Msgs::add("Account Created");
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
            $res = '<li class="menu_create_account"><a class="unread_link" href="?page=accounts">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$globe).'" alt="" '.'width="16" height="16" /> ';
            }
            $res .= $this->trans('Accounts').'</a></li>';
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
        return '<div class="content_title">'.$this->trans('Accounts').'</div>'.
            '<div class="settings_subtitle">'.$this->trans('Create Account').'</div>'.
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
class Hm_Output_user_list extends Hm_Output_Module {
    protected function output() {
        $res = '<br /><div class="settings_subtitle">'.$this->trans('Existing Accounts').'</div>';
        $res .= '<table class="user_list"><thead></thead><tbody>';
        if ($this->get('is_mobile')) {
            $width = 2;
        }
        else {
            $width = 6;
        }
        $count = 0;
        foreach ($this->get('user_list', array()) as $user) {
            if ($count == 0) {
                $res .= '<tr>';
            }
            $res .= '<td><form class="delete_user_form" action="?page=accounts" method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />'.
                '<input name="delete_username" type="hidden" value="'.
                $this->html_safe($user['username']).'" /><input class="user_delete" type="submit" '.
                'value=" X " /></form>';
            $res .= ' '.$this->html_safe($user['username']).'</td>';
            $count++;
            if ($count == $width) {
                $res .= '</tr>';
                $count = 0;
            }
        }
        if ($count != $width) {
            $res .= '</tr>';
        }
        $res .= '</table>';
        return $res;
    }
}

/**
 * Adds a link to the change password page to the folder list
 * @subpackage account/output
 */
class Hm_Output_change_password_link extends Hm_Output_Module {
    protected function output() {
        if ($this->get('internal_users')) {
            $res = '<li class="menu_change_password"><a class="unread_link" href="?page=change_password">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$key).'" alt="" width="16" height="16" /> ';
            }
            $res .= $this->trans('Password').'</a></li>';
            $this->concat('formatted_folder_list', $res);
        }
    }
}


/**
 * @subpackage account/output
 */
class Hm_Output_change_password extends Hm_Output_Module {
    protected function output() {
        $res = '';
        if ($this->get('internal_users')) {
            $res .= '<div class="chg_pass_page"><div class="content_title">'.$this->trans('Change Password').'</div>'.
                '<div class="change_pass"><form method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />'.
                '<label class="screen_reader" for="old_pass">'.$this->trans('Current password').'</label>'.
                '<input required type="password" id="old_pass" name="old_pass" placeholder="'.$this->trans('Current password').'" /><br />'.
                '<label class="screen_reader" for="new_pass1">'.$this->trans('New password').'</label>'.
                '<input required type="password" id="new_pass1" name="new_pass1" placeholder="'.$this->trans('New password').'" /><br />'.
                '<label class="screen_reader" for="new_pass2">'.$this->trans('New password again').'</label>'.
                '<input required type="password" id="new_pass2" name="new_pass2" placeholder="'.$this->trans('New password again').'" /><br />'.
                '<input type="submit" name="change_password" value="'.$this->trans('Update').'" />';
            $res .= '</form></div></div>';
        }
        return $res;
    }
}


