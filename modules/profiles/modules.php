<?php

/**
 * Profile modules
 * @package modules
 * @subpackage profile
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/profiles/hm-profiles.php';

/**
 * @subpackage profile/handler
 */
class Hm_Handler_profile_edit_data extends Hm_Handler_Module {
    public function process() {
        $id = false;
        if (array_key_exists('profile_id', $this->request->get)) {
            $id = $this->request->get['profile_id'];
        }
        $accounts = $this->get('profiles');
        if ($id !== false) {
            if (count($accounts) > $id) {
                $this->out('edit_profile', $accounts[$id]);
                $this->out('default_email_domain', $this->config->get('default_email_domain'));
                $this->out('edit_profile_id', $id);
            }
        }
        else {
            $this->out('new_profile_id', count($accounts));
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_compose_profile_data extends Hm_Handler_Module {
    public function process() {
        $profiles = new Hm_Profiles($this);
        $compose_profiles = array();
        $all_profiles = array();
        foreach ($profiles->list_all() as $id => $vals) {
            $vals['id'] = $id;
            if ($vals['smtp_id'] !== false && $vals['smtp_id'] !== '') {
                $compose_profiles[] = $vals;
            }
            $all_profiles[] = $vals;
        }
        $this->out('compose_profiles', $compose_profiles);
        $this->out('profiles', $all_profiles);
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_process_profile_delete extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('profile_delete', 'profile_id'));
        if (!$success) {
            return;
        }
        $data = $this->get('profiles');
        if (count($data) > $form['profile_id']) {
            $profiles = new Hm_Profiles($this);
            $del_profile = $profiles->get($form['profile_id']);
            if ($del_profile && array_key_exists('autocreate', $del_profile)) {
                Hm_Msgs::add('ERRAutomatically created profile cannot be deleted');
                return;
            }
            if ($profiles->del($form['profile_id'])) {
                $this->session->record_unsaved('Profile deleted');
                Hm_Msgs::add('Profile Deleted');
                $profiles->save($this->user_config);
                $user_data = $this->user_config->dump();
                $this->session->set('user_data', $user_data);
            }
        }
    }
}
/**
 * @subpackage profile/handler
 */
class Hm_Handler_process_profile_update extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('profile_delete', $this->request->post)) {
            return;
        }
        list($success, $form) = $this->process_form(array('profile_id', 'profile_address',
            'profile_smtp', 'profile_replyto', 'profile_name', 'profile_imap'));
        if (!$success) {
            return;
        }
        $default = false;
        $sig = '';
        $user = false;
        $server = false;

        $imap_server = explode('|', $form['profile_imap']);
        if (count($imap_server) == 2) {
            $server = $imap_server[0];
            $user = $imap_server[1];
        }
        if (!$user || !$server || !$form['profile_name']) {
            return;
        }

        if (array_key_exists('profile_sig', $this->request->post)) {
            $sig = $this->request->post['profile_sig'];
        }
        if (array_key_exists('profile_default', $this->request->post)) {
            $default = true;
        }

        $data = $this->get('profiles');
        $profile = array(
            'name' => html_entity_decode($form['profile_name'], ENT_QUOTES),
            'sig' => $sig,
            'smtp_id' => $form['profile_smtp'],
            'replyto' => $form['profile_replyto'],
            'default' => $default,
            'address' => $form['profile_address'],
            'server' => $server,
            'user' => $user,
            'type' => 'imap'
        );
        $profiles = new Hm_Profiles($this);
        if (count($data) > $form['profile_id']) {
            if ($profiles->edit($form['profile_id'], $profile)) {
                $this->session->record_unsaved('Profile updated');
                Hm_Msgs::add('Profile Updated');
            }
        }
        else {
            if ($profiles->add($profile)) {
                $this->session->record_unsaved('Profile added');
                Hm_Msgs::add('Profile Added');
            }
        }
        if ($default) {
            $profiles->set_default($form['profile_id']);
        }
        $profiles->save($this->user_config);
        $user_data = $this->user_config->dump();
        $this->session->set('user_data', $user_data);
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_profile_data extends Hm_Handler_Module {
    public function process() {
        $accounts = array();
        $profiles = new Hm_Profiles($this);
        $this->out('profiles', $profiles->list_all());
    }
}

/**id
 * @subpackage profile/output
 */
class Hm_Output_profile_edit_form extends Hm_Output_Module {
    protected function output() {
        $new_id = $this->get('new_profile_id', -1);
        $res = '<div class="profile_content"><div class="content_title">'.$this->trans('Profiles');
        $smtp_servers = $this->get('smtp_servers', array());
        $imap_servers = $this->get('imap_servers', array());
        if ($this->get('edit_profile')) {
            $profile = $this->get('edit_profile');
            $id = $this->get('edit_profile_id');
            $res .= profile_form($profile, $id, $smtp_servers, $imap_servers, $this);
        }
        if ($new_id !== -1) {
            $res .= profile_form(array('default' => '', 'name' => '', 'address' => '', 'replyto' => '',
                'smtp_id' => '', 'sig' => '', 'user' => '', 'server' => ''), $new_id, $smtp_servers,
                $imap_servers, $this);
        }
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_profile_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$person).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Profiles').'</a></li>';
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
        return '<input type="hidden" value="'.$this->trans('You need at least one configured profile to sign messages').'" id="sign_msg" />'.
            '<input class="compose_sign" type="button" value="'.$this->trans('Sign').'" />';
    }
}

/**
 * @subpackage profile/output
 */
class Hm_Output_compose_signature_values extends Hm_Output_Module {
    protected function output() {
        $res = '<script type="text/javascript">var profile_signatures = {';
        $sigs = array();
        $used = array();
        $profiles = $this->get('profiles');
        foreach ($this->get('compose_profiles', array()) as $vals) {
            $smtp_profiles = profiles_by_smtp_id($profiles, $vals['smtp_id']);
            if (count($smtp_profiles) > 0) {
                foreach ($smtp_profiles as $index => $smtp_vals) {
                    if (in_array($smtp_vals['id'], $used, true)) {
                        continue;
                    }
                    if (strlen(trim($smtp_vals['sig']))) {
                        $sigs[] = sprintf("%s: \"\\n%s\\n\"", $smtp_vals['smtp_id'].'.'.($index+1), $this->html_safe(str_replace("\r\n", "\\n", $smtp_vals['sig'])));
                        $used[] = $smtp_vals['id'];
                    }
                }
            }
            else {
                if (in_array($vals['id'], $used, true)) {
                    continue;
                }
                if (strlen(trim($vals['sig']))) {
                    $sigs[] = sprintf("%s: \"\\n%s\\n\"", $vals['smtp_id'], $this->html_safe(str_replace("\r\n", "\\n", $vals['sig'])));
                    $used[] = $vals['id'];
                }
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
        $profiles = $this->get('profiles');
        $res = '';
        if (count($profiles) > 0) {
            $smtp_servers = $this->get('smtp_servers', array());
            $res .= '<table class="profile_details"><tr>'.
                '<th>'.$this->trans('Display Name').'</th>'.
                '<th class="profile_fld">'.$this->trans('IMAP Server').'</th>'.
                '<th class="profile_fld">'.$this->trans('Username').'</th>'.
                '<th class="profile_fld">'.$this->trans('E-mail Address').'</th>'.
                '<th class="profile_fld">'.$this->trans('Reply-to').'</th>'.
                '<th class="profile_fld">'.$this->trans('SMTP Server').'</th>'.
                '<th class="profile_fld">'.$this->trans('Signature').'</th>'.
                '<th class="profile_fld">'.$this->trans('Default').'</th>'.
                '<th></th></tr>';

            foreach ($profiles as $id => $profile) {
                $smtp = '';
                    if ($profile['smtp_id'] !== false && array_key_exists($profile['smtp_id'], $smtp_servers)) {
                        $smtp = $smtp_servers[$profile['smtp_id']]['name'];
                    }
                $res .= '<tr>'.
                    '<td>'.$this->html_safe($profile['name']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($profile['server']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($profile['user']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($profile['address']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($profile['replyto']).'</td>'.
                    '<td class="profile_fld">'.$this->html_safe($smtp).'</td>'.
                    '<td class="profile_fld">'.(strlen($profile['sig']) > 0 ? $this->trans('Yes') : $this->trans('No')).'</td>'.
                    '<td class="profile_fld">'.($profile['default'] ? $this->trans('Yes') : $this->trans('No')).'</td>'.
                    '<td><a href="?page=profiles&amp;profile_id='.$this->html_safe($id).'" title="'.$this->trans('Edit').'">'.
                    '<img alt="'.$this->trans('Edit').'" width="16" height="16" src="'.Hm_Image_Sources::$cog.'" /></a></td>'.
                    '</tr>';
            }
            $res .= '</table>';
        }
        else {
            $res .= '<div class="profiles_empty">'.$this->trans('No Profiles Found').'</div>';
        }
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage profile/functions
 */
if (!hm_exists('profile_form')) {
function profile_form($form_vals, $id, $smtp_servers, $imap_servers, $out_mod) {
    $res = '';
    if ($form_vals['name']) {
        $res .= '<img class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" width="8" height="8" />';
        $res .= $out_mod->html_safe($form_vals['name']);
    }
    else {
        $res .= '<img class="refresh_list add_profile" width="24" height="24" src="'.
            Hm_Image_Sources::$plus.'" title="'.$out_mod->trans('Add a profile').'" alt="'.$out_mod->trans('Add a profile').'" />';
    }
    $res .= '</div><div class="edit_profile" '.($form_vals['name'] ? '' : 'style="display: none;"').'><form method="post" action="?page=profiles">';
    $res .= '<input type="hidden" name="profile_id" value="'.$out_mod->html_safe($id).'" />';
    $res .= '<input type="hidden" name="hm_page_key" value="'.$out_mod->html_safe(Hm_Request_Key::generate()).'" />';
    $res .= '<table><tr><th>'.$out_mod->trans('Display Name').' *</th><td><input type="text" required name="profile_name" value="'.$out_mod->html_safe($form_vals['name']).'" /></td></tr>';
    $res .= '<tr><th>'.$out_mod->trans('E-mail Address').' *</th><td><input type="email" required name="profile_address" value="'.$out_mod->html_safe($form_vals['address']).'" /></td></tr>';
    $res .= '<tr><th>'.$out_mod->trans('Reply-to').' *</th><td><input type="email" required name="profile_replyto" value="'.$out_mod->html_safe($form_vals['replyto']).'" /></td></tr>';
    $res .= '<tr><th>'.$out_mod->trans('IMAP Server').' *</th><td><select required name="profile_imap">';
    foreach ($imap_servers as $id => $server) {
        $res .= '<option ';
        if ($server['user'] == $form_vals['user'] && $server['server'] == $form_vals['server']) {
            $res .= 'selected="selected"';
        }
        $res .= 'value="'.$out_mod->html_safe($server['server'].'|'.$server['user']).'">'.$out_mod->html_safe($server['name']).'</option>';
    }
    $res .= '</select></td></tr>';
    $res .= '<tr><th>'.$out_mod->trans('SMTP Server').' *</th><td><select required name="profile_smtp">';
    foreach ($smtp_servers as $id => $server) {
        $res .= '<option ';
        if ($id == $form_vals['smtp_id']) {
            $res .= 'selected="selected"';
        }
        $res .= 'value="'.$out_mod->html_safe($id).'">'.$out_mod->html_safe($server['name']).'</option>';
    }
    $res .= '</select></td></tr>';
    $res .= '<tr><th>'.$out_mod->trans('Signature').'</th><td><textarea cols="80" rows="4" name="profile_sig">';
    $res .= $out_mod->html_safe($form_vals['sig']).'</textarea></td></tr>';
    $res .= '<tr><th>'.$out_mod->trans('Set as default').'</th><td><input type="checkbox" ';
    if ($form_vals['default']) {
        $res .= 'checked="checked"';
    }
    $res .= 'name="profile_default" /></td></tr><tr><td></td><td>';
    if ($form_vals['name']) {
        $res .= '<input type="submit" class="profile_update" value="'.$out_mod->trans('Update').'" /> ';
        $res .= '<input type="submit" name="profile_delete" value="'.$out_mod->trans('Delete').'" /> ';
        $res .= '<a href="?page=profiles"><input type="button" value="'.$out_mod->trans('Cancel').'" /></a>';
    }
    else {
        $res .= '<input type="submit" class="submit_profile" value="'.$out_mod->trans('Create').'" />';
    }
    $res .= '</td></tr></table></form>';
    return $res;
}}

