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
            Hm_Msgs::add("New passwords don't not match", "warning");
            return;
        }
        $user = $this->session->get('username', false);
        if (!$this->session->auth($user, $form['old_pass'])) {
            Hm_Msgs::add("Current password is incorrect", "warning");
            return;
        }
        $user_config = load_user_config_object($this->config);
        if ($this->session->change_pass($user, $form['new_pass1'])) {
            Hm_Msgs::add("Password changed");
            $user_config->load($user, $form['old_pass']);
            try {
                $user_config->save($user, $form['new_pass1']);
            } catch (Exception $e) {
                Hm_Msgs::add('Could not save settings: ' . $e->getMessage(), 'warning');
            }
            return;
        }
        Hm_Msgs::add("An error Occurred", "danger");
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
        if ($form['delete_username'] === $this->session->get('username')) {
            Hm_Msgs::add('You cannot delete your own account', 'warning');
            return;
        }
        $dbh = Hm_DB::connect($this->config);
        if (Hm_DB::execute($dbh, 'delete from hm_user where username=?', array($form['delete_username']))) {
            Hm_Msgs::add('User account deleted');
        }
        else {
            Hm_Msgs::add('An error occurred deleting the account', 'danger');
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
            Hm_Msgs::add('Passwords did not match', 'warning');
            return;
        }
        $res = $this->session->create($form['create_username'], $form['create_password']);
        if ($res === 1) {
            Hm_Msgs::add("That username is already in use", "warning");
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
            $res = '<li class="menu_create_account"><a class="unread_link" href="' . $this->build_page_url('accounts') . '">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<i class="bi bi-people-fill menu-icon"></i> ';
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
            Hm_Dispatch::page_redirect($this->build_page_url('home'));
        }
        return '<div class="accounts_page px-0">'.
            '<div class="content_title px-3">'.$this->trans('Accounts').'</div>'.
            '<div class="settings_subtitle p-3 border-bottom">'.$this->trans('Create Account').'</div>'.
            '<div class="create_user row px-3 mt-3">'.
                '<div class="col-lg-4 col-sm-12">'.
                    '<form method="POST" autocomplete="off">'.
                        '<input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />'.
                        '<input style="display:none" type="text" name="fake_username" />'.
                        '<input style="display:none" type="password" name="fake_password" />'.
                        '<div class="form-floating mb-3">'.
                            '<input required type="text" id="create_username" name="create_username" class="form-control" placeholder="'.$this->trans('Username').'" value="">'.
                            '<label for="create_username">'.$this->trans('Username').'</label>'.
                        '</div>'.
                        '<div class="form-floating mb-3">'.
                            '<input required type="password" id="create_password" name="create_password" class="form-control" placeholder="'.$this->trans('Password').'">'.
                            '<label for="create_password">'.$this->trans('Password').'</label>'.
                        '</div>'.
                        '<div class="form-floating mb-3">'.
                            '<input required type="password" id="create_password_again" name="create_password_again" class="form-control" placeholder="'.$this->trans('Password Again').'">'.
                            '<label for="create_password_again">'.$this->trans('Password Again').'</label>'.
                        '</div>'.
                        '<input type="submit" name="create_hm_user" class="btn btn-primary" value="'.$this->trans('Create').'" />'.
                    '</form>'.
                '</div>'.
            '</div>';
    }
}

/**
 * @subpackage account/output
 */
class Hm_Output_user_list extends Hm_Output_Module {
    protected function output() {
        $current_user = $this->get('username', '');
        $users = $this->get('user_list', array());
        $res = '<div class="settings_subtitle p-3 border-bottom mt-3">'.$this->trans('Existing Accounts').'</div>';
        $res .= '<div class="row px-3 pb-3"><div class="col-lg-8 col-xl-6">';
        $res .= '<div class="table-responsive">';
        if (!$users) {
            $res .= '<div class="d-flex flex-column align-items-center justify-content-center p-5">'.
                '<i class="bi bi-people fs-4"></i>'.
                '<span>'.$this->trans('No accounts found').'</span>'.
                '</div>';
        }
        else {
            $res .= '<table class="table table-striped user_list">'.
                '<thead><tr>'.
                '<th>'.$this->trans('Username').'</th>'.
                '<th class="text-end"></th>'.
                '</tr></thead><tbody>';
            foreach ($users as $user) {
                $username = $user['username'];
                $is_current = ($username === $current_user);
                $res .= '<tr><td>'.$this->html_safe($username);
                if ($is_current) {
                    $res .= ' <span class="badge text-bg-secondary">'.$this->trans('You').'</span>';
                }
                $res .= '</td><td class="text-end">';
                if ($is_current) {
                    $res .= '<button type="button" class="btn btn-link p-0 text-muted border-0" disabled '.
                        'title="'.$this->trans('You cannot delete your own account').'">'.
                        '<i class="bi bi-trash-fill"></i></button>';
                }
                else {
                    $confirm_msg = sprintf($this->trans('Are you sure you want to delete the account "%s"? This cannot be undone.'), $username);
                    $res .= '<form class="delete_user_form d-inline" action="'.$this->build_page_url('accounts').'" method="POST">'.
                        '<input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />'.
                        '<input name="delete_username" type="hidden" value="'.$this->html_safe($username).'" />'.
                        '<button type="submit" class="btn btn-link p-0 text-danger border-0 user_delete" '.
                        'title="'.$this->trans('Delete').'" aria-label="'.$this->trans('Delete').'" '.
                        'onclick="if (typeof applyAccountsPageHandlers === \'function\') { return true; } return confirm('.htmlspecialchars(json_encode($confirm_msg), ENT_QUOTES, 'UTF-8').');">'.
                        '<i class="bi bi-trash-fill"></i></button></form>';
                }
                $res .= '</td></tr>';
            }
            $res .= '</tbody></table>';
        }
        $res .= '</div></div></div>';
        $res .= '<div class="modal fade" id="confirmDeleteAccountModal" tabindex="-1" aria-labelledby="confirmDeleteAccountModalLabel" aria-hidden="true">'.
            '<div class="modal-dialog">'.
            '<div class="modal-content">'.
            '<div class="modal-header">'.
            '<h5 class="modal-title" id="confirmDeleteAccountModalLabel">'.$this->trans('Delete account').'</h5>'.
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.$this->trans('Close').'"></button>'.
            '</div>'.
            '<div class="modal-body">'.
            '<p class="mb-0" id="confirmDeleteAccountMessage"></p>'.
            '</div>'.
            '<div class="modal-footer">'.
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'.$this->trans('Cancel').'</button>'.
            '<button type="button" class="btn btn-danger" id="confirmDeleteAccountBtn">'.$this->trans('Delete').'</button>'.
            '</div></div></div></div>';
        $res .= '</div>';
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
            $res = '<li class="menu_change_password"><a class="unread_link" href="'.$this->build_page_url('change_password').'">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<i class="bi bi-key-fill menu-icon"></i>';
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
            $res .= '<div class="chg_pass_page px-0">
                        <div class="content_title px-3">'.$this->trans('Change Password').'</div>
                        <div class="change_pass row px-3 mt-3">
                            <div class="col-lg-4 col-sm-12">
                                <form method="POST">
                                    <input type="hidden" name="hm_page_key" value="'.Hm_Request_Key::generate().'" />

                                    <div class="form-floating mb-3">
                                        <input required type="password" id="old_pass" name="old_pass" class="form-control" placeholder="'.$this->trans('Current password').'">
                                        <label for="old_pass">'.$this->trans('Current password').'</label>
                                    </div>

                                    <div class="form-floating mb-3">
                                        <input required type="password" id="new_pass1" name="new_pass1" class="form-control" placeholder="'.$this->trans('New password').'">
                                        <label for="new_pass1">'.$this->trans('New password').'</label>
                                    </div>

                                    <div class="form-floating mb-3">
                                        <input required type="password" id="new_pass2" name="new_pass2" class="form-control" placeholder="'.$this->trans('New password again').'">
                                        <label for="new_pass2">'.$this->trans('New password again').'</label>
                                    </div>

                                    <input type="submit" name="change_password" class="btn btn-primary" value="'.$this->trans('Update').'">
                                </form>
                            </div>
                        </div>
                    </div>';
        }
        return $res;
    }
}
