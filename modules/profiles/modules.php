<?php

/**
 * Profile modules
 * @package modules
 * @subpackage profile
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage profile/handler
 */
class Hm_Handler_profile_edit_data extends Hm_Handler_Module {
    public function process() {
        $id = false;
        if (array_key_exists('profile_id', $this->request->get)) {
            $id = $this->request->get['profile_id'];
        }
        if ($id !== false) {
            $accounts = $this->get('account_profiles');
            if (count($accounts) > $id) {
                $this->out('edit_profile', $accounts[$id]);
                $this->out('edit_profile_id', $id);
            }
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_compose_profile_data extends Hm_Handler_Module {
    public function process() {
        $profiles = array();
        foreach ($this->user_config->dump() as $name => $vals) {
            if (preg_match("/^profile_/", $name) && is_array($vals)) {
                if (array_key_exists('profile_smtp', $vals)) {
                    $profiles[$vals['profile_smtp']] = $vals;
                }
            }
        }
        $this->out('compose_profiles', $profiles);
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_process_profile_update extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('profile_id'));
        if ($success) {
            $smtp = 0;
            $replyto = '';
            $name = '';
            $sig = '';
            if (array_key_exists('profile_smtp', $this->request->post)) {
                $smtp = $this->request->post['profile_smtp'];
            }
            if (array_key_exists('profile_replyto', $this->request->post)) {
                $replyto = $this->request->post['profile_replyto'];
            }
            if (array_key_exists('profile_name', $this->request->post)) {
                $name = $this->request->post['profile_name'];
            }
            if (array_key_exists('profile_sig', $this->request->post)) {
                $sig = $this->request->post['profile_sig'];
            }
            $data = $this->get('account_profiles');
            if (count($data) > $form['profile_id']) {
                $current = $data[$form['profile_id']];
                $profile = array(
                    'profile_name' => $name,
                    'profile_sig' => $sig,
                    'profile_smtp' => $smtp,
                    'profile_replyto' => $replyto
                );
                $this->user_config->set('profile_'.$current['type'].'_'.$current['server'].'_'.$current['user'], $profile);
                $this->session->record_unsaved('Profile updated');
                Hm_Msgs::add('Profile Updated');
            }
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_profile_data extends Hm_Handler_Module {
    public function process() {
        $accounts = array();
        if ($this->module_is_supported('imap')) {
            foreach (Hm_IMAP_List::dump() as $server) {
                $server['profile_details'] = $this->user_config->get('profile_imap_'.$server['server'].'_'.$server['user'], array(
                    'profile_name' => '', 'profile_replyto' => '', 'profile_smtp' => '', 'profile_sig' => ''));
                $server['type'] = 'imap';
                $accounts[] = $server;
            }
        }
        if ($this->module_is_supported('pop3')) {
            foreach (Hm_POP3_List::dump() as $server) {
                $server['profile_details'] = $this->user_config->get('profile_pop3_'.$server['server'].'_'.$server['user'], array(
                    'profile_name' => '', 'profile_replyto' => '', 'profile_smtp' => '', 'profile_sig' => ''));
                $server['type'] = 'pop3';
                $accounts[] = $server;
            }
        }
        $used_smtp_servers = array();
        foreach ($accounts as $account) {
            if ($account['profile_details']['profile_smtp'] != '') {
                $used_smtp_servers[] = $account['profile_details']['profile_smtp'];
            }
        }
        $this->out('account_profiles', $accounts);
        $this->out('used_smtp_servers', $used_smtp_servers);
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_edit_form extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="profile_content"><div class="content_title">'.$this->trans('Profiles');
        $smtp_servers = $this->get('smtp_servers', array());
        if ($this->get('edit_profile')) {
            $used = $this->get('used_smtp_servers', array());
            $data = $this->get('edit_profile');
            $id = $this->get('edit_profile_id');
            $profile = $data['profile_details'];
            if (!$profile['profile_replyto']) {
                $profile['profile_replyto'] = $data['user'];
            }
            $res .= '<img class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" width="8" height="8" />';
            $res .= $this->html_safe($data['name']).'</div>';
            $res .= '<div class="edit_profile"><form method="post" action="?page=profiles">';
            $res .= '<input type="hidden" name="profile_id" value="'.$this->html_safe($id).'" />';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<table><tr><th>'.$this->trans('Display Name').'</th><td><input type="text" name="profile_name" value="'.$this->html_safe($profile['profile_name']).'" /></td></tr>';
            $res .= '<tr><th>'.$this->trans('Reply-to Address').'</th><td><input type="email" name="profile_replyto" value="'.$this->html_safe($profile['profile_replyto']).'" /></td></tr>';
            $res .= '<tr><th>'.$this->trans('SMTP Server').'</th><td><select name="profile_smtp">';
            foreach ($smtp_servers as $id => $server) {
                $res .= '<option ';
                if (in_array($id, $used, true)) {
                    $res .= 'disabled="disabled" ';
                }
                if ($id == $profile['profile_smtp']) {
                    $res .= 'selected="selected"';
                }
                $res .= 'value="'.$this->html_safe($id).'">'.$this->html_safe($server['name']).'</option>';
            }
            $res .= '</select></td></tr>';
            $res .= '<tr><th>'.$this->trans('Signature').'</th><td><textarea cols="80" rows="4" name="profile_sig">';
            $res .= $this->html_safe($profile['profile_sig']).'</textarea></td></tr>';
            $res .= '<tr><td></td><td><input type="submit" value="'.$this->trans('Update').'" /></td></tr></table>';
            $res .= '</form></div>';
        }
        else {
            $res .= '</div>';
        }
        return $res;
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$person).
            '" alt="" width="16" height="16" /> '.$this->trans('Profiles').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_compose_signature_button extends Hm_Output_Module {
    protected function output() {
        return '<input class="compose_sign" type="button" value="'.$this->trans('Sign').'" />';
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_compose_signature_values extends Hm_Output_Module {
    protected function output() {
        $res = '<script type="text/javascript">var profile_signatures = {';
        $sigs = array();
        foreach ($this->get('compose_profiles', array()) as $smtp_id => $vals) {
            if (strlen(trim($vals['profile_sig']))) {
                $sigs[] = sprintf("%s: \"\\n%s\\n\"", $smtp_id, $this->html_safe(str_replace("\r\n", "\\n", $vals['profile_sig'])));
            }
        }
        $res .= implode(', ', $sigs).'}</script>';
        return $res;
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_content extends Hm_Output_Module {
    protected function output() {
        $profiles = $this->get('account_profiles');
        $res = '';
        if (count($profiles) > 0) {
            $smtp_servers = $this->get('smtp_servers', array());
            $res .= '<table class="profile_details"><tr>'.
                '<th></th><th>'.$this->trans('Name').'</th>'.
                '<th>'.$this->trans('Server').'</th>'.
                '<th>'.$this->trans('Username').'</th>'.
                '<th>'.$this->trans('Display Name').'</th>'.
                '<th>'.$this->trans('Reply-to').'</th>'.
                '<th>'.$this->trans('SMTP Server').'</th>'.
                '<th>'.$this->trans('Signature').'</th>'.
                '</tr>';
            foreach ($profiles as $id => $profile) {
                $smtp = '';
                if (array_key_exists('profile_smtp', $profile['profile_details'])) {
                    if (array_key_exists($profile['profile_details']['profile_smtp'], $smtp_servers)) {
                        $smtp = $smtp_servers[$profile['profile_details']['profile_smtp']]['name'];
                    }
                }
                $res .= '<tr>'.
                    '<td><a href="?page=profiles&amp;profile_id='.$this->html_safe($id).'" title="Edit">'.
                    '<img alt="'.$this->trans('Edit').'" width="16" height="16" src="'.Hm_Image_Sources::$cog.'" /></a></td>'.
                    '<td>'.$this->html_safe($profile['name']).'</td>'.
                    '<td>'.$this->html_safe($profile['server']).'</td>'.
                    '<td>'.$this->html_safe($profile['user']).'</td>'.
                    '<td>'.(array_key_exists('profile_name', $profile['profile_details']) ? $this->html_safe($profile['profile_details']['profile_name']) : '').'</td>'.
                    '<td>'.(array_key_exists('profile_replyto', $profile['profile_details']) ? $this->html_safe($profile['profile_details']['profile_replyto']) : '').'</td>'.
                    '<td>'.$this->html_safe($smtp).'</td>'.
                    '<td>'.(array_key_exists('profile_sig', $profile['profile_details']) && strlen($profile['profile_details']['profile_sig']) > 0 ? $this->trans('Yes') : $this->trans('No')).'</td>'.
                    '</tr>';
            }
            $res .= '</table>';
        }
        else {
            $res .= '<div class="profiles_empty">'.$this->trans('No IMAP or POP3 servers configured').'</div>';
        }
        $res .= '</div>';
        return $res;
    }
}
