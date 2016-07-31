<?php

/**
 * @package modules
 * @subpackage recover_settings
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage recover_settings/handler
 */
class Hm_Handler_process_recover_settings_form extends Hm_Handler_login {
    public function process() {
        list($success, $form) = $this->process_form(array('recover_settings', 'old_password_recover', 'new_password_recover'));
        if ($success) {
            $user = $this->session->get('username');
            if (!$this->session->auth($user, $form['new_password_recover'])) {
                Hm_Msgs::add('ERRError validating your current password');
                return;
            }
            $data = $this->session->get('old_settings_str');
            if (!$data) {
                Hm_Msgs::add('ERRNo old settings to recover');
                return;
            }
            $settings = $this->user_config->decode(Hm_Crypt::plaintext($data, $form['old_password_recover']));
            if (!is_array($settings) || count($settings) == 0) {
                Hm_Msgs::add('ERRUnable to recover your settings');
                return;
            }
            foreach ($settings as $name => $val) {
                $this->user_config->set($name, $val);
            }
            $this->user_config->save($user, $form['new_password_recover']);
            Hm_Msgs::add('Settings recovered');
            $this->session->set('load_recover_options', false);
            $this->session->set('old_settings_str', '');
            $this->out('reload_folders', true, false);
        }
    }
}

/**
 * @subpackage recover_settings/handler
 */
class Hm_Handler_check_for_lost_settings extends Hm_Handler_login {
    public function process() {
        if ($this->session->loaded && $this->get('load_settings_failed') &&
            in_array($this->session->auth_class, array('Hm_Auth_IMAP', 'Hm_Auth_POP3', true))) {
            $this->session->set('load_recover_options', true);
            $this->session->set('old_settings_str', $this->user_config->encrypted_str);
            Hm_Msgs::add('ERRUnable to load your settings! You may be able to recover them on the "Recover Settings" page in the Main menu.');
        }
        $this->out('load_recover_options', $this->session->get('load_recover_options'));
        $this->out('auth_type', $this->session->auth_class);
    }
}

/**
 * @subpackage recover_settings/output
 */
class Hm_Output_recover_settings_page_link extends Hm_Output_Module {
    protected function output() {
        if ($this->get('load_recover_options')) {
            $res = '<li class="menu_recover_settings"><a class="unread_link" href="?page=recover_settings">'.
                '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$unlocked).
                '" alt="" width="16" height="16" /> '.$this->trans('Recover Settings').'</a></li>';
            if ($this->format == 'HTML5') {
                return $res;
            }
            $this->concat('formatted_folder_list', $res);
        }
    }
}

/**
 * @subpackage recover_settings/output
 */
class Hm_Output_recover_settings_page extends Hm_Output_Module {
    protected function output() {
        $auth = $this->get('auth_type');
        if ($auth == 'Hm_Auth_IMAP') {
            $type = 'IMAP';
        }
        else {
            $type = 'POP3';
        }
        $res = '<div class="recover_settings_content"><div class="content_title">'.$this->trans('Recover Settings').'</div>';
        $res .= '<div class="recover_form">'.$this->trans('Settings detected that we could not decrypt.').' <b>';
        $res .= sprintf($this->trans('Did your %s password change since you last logged into Cypht?'), $type);
        $res .= '</b> Your settings may be recovered by entering your old and new passwords in the form below.';
        $res .= '<form method="post" action="?page=recover_settings"><br />';
        $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
        $res .= '<input required type="password" name="old_password_recover" placeholder="'.$this->trans('Old password').'" /><br />';
        $res .= '<input required type="password" name="new_password_recover" placeholder="'.$this->trans('Current password').'" /><br />';
        $res .= '<input type="submit" name="recover_settings" value="'.$this->trans('Recover').'" />';
        $res .= '</form></div></div>';
        return $res;
    }
}
