<?php

/**
 * Profile modules
 * @package modules
 * @subpackage profile
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/profiles/hm-profiles.php';
require APP_PATH.'modules/profiles/functions.php';

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

        foreach ($accounts as $acc) {
            if ($acc['id'] == $id) {
                $account = $acc;
            }
        }

        if ($id !== false) {
            $this->out('edit_profile', $account);
            $this->out('default_email_domain', $this->config->get('default_email_domain'));
            $this->out('edit_profile_id', $id);
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
        Hm_Profiles::init($this);
        $compose_profiles = array();
        foreach (Hm_Profiles::getAll() as $id => $vals) {
            if (! empty($vals['smtp_id'])) {
                $compose_profiles[] = $vals;
            }
        }
        $this->out('compose_profiles', $compose_profiles);
        $this->out('profiles', Hm_Profiles::getAll());
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

        if (($profile = Hm_Profiles::get($form['profile_id']))) {
            if (array_key_exists('autocreate', $profile)) {
                Hm_Msgs::add('Automatically created profile cannot be deleted', 'warning');
                return;
            }
            Hm_Profiles::del($form['profile_id']);
            Hm_Msgs::add('Profile Deleted');
        } else {
            Hm_Msgs::add('Profile ID not found', 'warning');
            return;
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
            'profile_smtp', 'profile_replyto', 'profile_name', 'profile_imap', 'profile_rmk'));
        if (!$success) {
            return;
        }
        $default = false;
        $sig = '';
        $rmk = '';
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
        if (array_key_exists('profile_rmk', $this->request->post)) {
            $rmk = $this->request->post['profile_rmk'];
        }
        if (array_key_exists('profile_default', $this->request->post)) {
            $default = true;
        }

        $profile = array(
            'name' => html_entity_decode($form['profile_name'], ENT_QUOTES),
            'sig' => $sig,
            'rmk' => $rmk,
            'smtp_id' => $form['profile_smtp'],
            'replyto' => $form['profile_replyto'],
            'default' => $default,
            'address' => $form['profile_address'],
            'server' => $server,
            'user' => $user,
            'type' => 'imap'
        );
        if (Hm_Profiles::get($form['profile_id'])) {
            $profile['id'] = $form['profile_id'];
            Hm_Profiles::edit($form['profile_id'], $profile);
        } else {
            $profiles = $this->get('profiles');

            foreach ($profiles as $existing_profile) {
                if (
                    ($existing_profile["address"] === $profile["address"] && $existing_profile["smtp_id"] === $profile["smtp_id"]) ||
                    ($existing_profile["replyto"] === $profile["replyto"] && $existing_profile["smtp_id"] === $profile["smtp_id"])
                ) {
                    Hm_Msgs::add('Profile with this email address or reply-to address already exists', 'warning');
                    return;
                }
            }
            Hm_Profiles::add($profile);
            Hm_Msgs::add('Profile Created');
        }

        if ($default) {
            Hm_Profiles::setDefault($form['profile_id']);
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_profile_data extends Hm_Handler_Module {
    public function process() {
        Hm_Profiles::init($this);
        $this->out('profiles', Hm_Profiles::getAll());
    }
}

/**id
 * @subpackage profile/output
 */
class Hm_Output_profile_edit_form extends Hm_Output_Module {
    protected function output() {
        $new_id = $this->get('new_profile_id', -1);
        $res = '<div class="profile_content p-0"><div class="content_title px-3 d-flex justify-content-between"><span class="profile_content_title">'.$this->trans('Profiles').'</span>';
        $smtp_servers = $this->get('smtp_servers', array());
        $imap_servers = $this->get('imap_servers', array());
        if ($this->get('edit_profile')) {
            $profile = $this->get('edit_profile');
            $id = $this->get('edit_profile_id');
            $res .= profile_form($profile, $id, $smtp_servers, $imap_servers, $this);
        }
        if ($new_id !== -1) {
            $res .= profile_form(array('default' => '', 'name' => '', 'address' => '', 'replyto' => '',
                'smtp_id' => '', 'sig' => '', 'user' => '', 'server' => '', 'rmk' => ''), $new_id, $smtp_servers,
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
            $res .= '<i class="bi bi-person-fill menu-icon"></i>';
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
            '<input class="compose_sign btn btn-light float-end mt-3 me-2 border" type="button" value="'.$this->trans('Sign').'" />';
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
                    if (mb_strlen(trim($smtp_vals['sig']))) {
                        $sigs[] = sprintf("\"%s\": \"\\n%s\\n\"", $smtp_vals['smtp_id'].'.'.($index+1), $this->html_safe(str_replace("\r\n", "\\n", $smtp_vals['sig'])));
                        $used[] = $smtp_vals['id'];
                    }
                }
            }
            else {
                if (in_array($vals['id'], $used, true)) {
                    continue;
                }
                if (mb_strlen(trim($vals['sig']))) {
                    $sigs[] = sprintf("\"%s\": \"\\n%s\\n\"", $vals['smtp_id'], $this->html_safe(str_replace("\r\n", "\\n", $vals['sig'])));
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
            $res .= '<div class="table-responsive p-3"><table class="table table-striped"><tr>'.
                '<th>'.$this->trans('Display Name').'</th>'.
                '<th class="d-none d-sm-table-cell">'.$this->trans('IMAP Server').'</th>'.
                '<th class="d-none d-sm-table-cell">'.$this->trans('Username').'</th>'.
                '<th class="d-none d-sm-table-cell">'.$this->trans('E-mail Address').'</th>'.
                '<th class="d-none d-sm-table-cell">'.$this->trans('Reply-to').'</th>'.
                '<th class="d-none d-sm-table-cell">'.$this->trans('SMTP Server').'</th>'.
                '<th class="d-none d-sm-table-cell">'.$this->trans('Signature').'</th>'.
                '<th class="d-none d-sm-table-cell">'.$this->trans('Remark').'</th>'.
                '<th class="d-none d-sm-table-cell">'.$this->trans('Default').'</th>'.
                '<th></th></tr>';

            foreach ($profiles as $id => $profile) {
                $smtp = '';
                   if (isset($profile['smtp_id']) && is_scalar($profile['smtp_id']) && array_key_exists($profile['smtp_id'], $smtp_servers)) {
                        $smtp = $smtp_servers[$profile['smtp_id']]['name'];
                   }
                $res .= '<tr>'.
                    '<td>'.$this->html_safe($profile['name']).'</td>'.
                    '<td class="d-none d-sm-table-cell">'.$this->html_safe($profile['server']).'</td>'.
                    '<td class="d-none d-sm-table-cell">'.$this->html_safe($profile['user']).'</td>'.
                    '<td class="d-none d-sm-table-cell">'.$this->html_safe($profile['address']).'</td>'.
                    '<td class="d-none d-sm-table-cell">'.$this->html_safe($profile['replyto']).'</td>'.
                    '<td class="d-none d-sm-table-cell">'.$this->html_safe($smtp).'</td>'.
                    '<td class="d-none d-sm-table-cell">'.(mb_strlen($profile['sig']) > 0 ? $this->trans('Yes') : $this->trans('No')).'</td>'.
                    '<td class="d-none d-sm-table-cell">'.(mb_strlen($profile['rmk']) > 0 ? $this->trans('Yes') : $this->trans('No')).'</td>'.
                    '<td class="d-none d-sm-table-cell">'.($profile['default'] ? $this->trans('Yes') : $this->trans('No')).'</td>'.
                    '<td class="text-right"><a href="?page=profiles&amp;profile_id='.$this->html_safe($profile['id']).'" title="'.$this->trans('Edit').'">'.
                    '<i class="bi bi-gear-fill"></i></a></td>'.
                    '</tr>';
            }
            $res .= '</table></div>';
        }
        else {
            $res .= '<div class="d-flex flex-column align-items-center justify-content-center p-5 mt-5"><i class="bi bi-folder2-open fs-4"></i><span>'.$this->trans('No Profiles Found').'</span></div>';
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
        $res .= '<span>';
        $res .= '<i class="bi bi-caret-right-fill"></i>';
        $res .= $out_mod->html_safe($form_vals['name']);
        $res .= '</span>';
    }
    else {
        $res .= '<button class="refresh_list add_profile btn btn-light btn-sm d-flex" title="'.$out_mod->trans('Add a profile').'"><i class="bi bi-plus"></i><span class="d-none d-sm-table-cell">'.$out_mod->trans('Add a profile').'</span></button>';
    }
    $res .= '</div>';

    $res .= '<div class="edit_profile row p-3" '.($form_vals['name'] ? '' : 'style="display: none;"').'><div class="col-12 col-lg-8 col-xl-5">';

    $res .= '<form method="post" action="?page=profiles">';
    $res .= '<input type="hidden" name="profile_id" value="'.$out_mod->html_safe($id).'" />';
    $res .= '<input type="hidden" name="hm_page_key" value="'.$out_mod->html_safe(Hm_Request_Key::generate()).'" />';

    // Display Name
    $res .= '<div class="form-floating mb-3">';
    $res .= '<input type="text" required name="profile_name" class="form-control" value="'.$out_mod->html_safe($form_vals['name']).'" placeholder="'.$out_mod->trans('Display Name').' *">';
    $res .= '<label>'.$out_mod->trans('Display Name').' *</label></div>';

    // Email Address
    $res .= '<div class="form-floating mb-3">';
    $res .= '<input type="email" required name="profile_address" class="form-control" value="'.$out_mod->html_safe($form_vals['address']).'" placeholder="'.$out_mod->trans('E-mail Address').' *">';
    $res .= '<label>'.$out_mod->trans('E-mail Address').' *</label></div>';

    // Reply-to
    $res .= '<div class="form-floating mb-3">';
    $res .= '<input type="email" required name="profile_replyto" class="form-control" value="'.$out_mod->html_safe($form_vals['replyto']).'" placeholder="'.$out_mod->trans('Reply-to').' *">';
    $res .= '<label>'.$out_mod->trans('Reply-to').' *</label></div>';

    // IMAP Server
    $res .= '<div class="form-floating mb-3">';
    $res .= '<select required name="profile_imap" class="form-select">';
    foreach ($imap_servers as $id => $server) {
        $res .= '<option '.(($server['user'] == $form_vals['user'] && $server['server'] == $form_vals['server']) ? 'selected="selected"' : '').' value="'.$out_mod->html_safe($server['server'].'|'.$server['user']).'">'.$out_mod->html_safe($server['name']).'</option>';
    }
    $res .= '</select>';
    $res .= '<label>'.$out_mod->trans('IMAP Server').' *</label></div>';

    // SMTP Server
    $res .= '<div class="form-floating mb-3">';
    $res .= '<select required name="profile_smtp" class="form-select">';
    foreach ($smtp_servers as $id => $server) {
        $res .= '<option ';
        if ($server['id'] == $form_vals['smtp_id']) {
            $res .= 'selected="selected"';
        }
        $res .= 'value="'.$out_mod->html_safe($server['id']).'">'.$out_mod->html_safe($server['name']).'</option>';
    }
    $res .= '</select>';
    $res .= '<label>'.$out_mod->trans('SMTP Server').' *</label></div>';

    // Signature
    $res .= '<div class="form-floating mb-3">';
    $res .= '<textarea cols="80" rows="4" name="profile_sig" class="form-control" style="min-height : 120px" placeholder="'.$out_mod->trans('Signature').'">'.$out_mod->html_safe($form_vals['sig']).'</textarea>';
    $res .= '<label>'.$out_mod->trans('Signature').'</label></div>';

    // Remark
    $res .= '<div class="form-floating mb-3">';
    $res .= '<textarea cols="80" rows="4" name="profile_rmk" class="form-control" style="min-height : 120px" placeholder="'.$out_mod->trans('Remark').'">'.$out_mod->html_safe($form_vals['rmk']).'</textarea>';
    $res .= '<label>'.$out_mod->trans('Remark').'</label></div>';

    // Set as default
    $res .= '<div class="form-check mb-3">';
    $res .= '<input type="checkbox" class="form-check-input" '.($form_vals['default'] ? 'checked="checked"' : '').' name="profile_default">';
    $res .= '<label class="form-check-label">'.$out_mod->trans('Set as default').'</label></div>';

    // Submit buttons
    $res .= '<div>';
    if ($form_vals['name']) {
        $res .= '<input type="submit" class="btn btn-primary profile_update" value="'.$out_mod->trans('Update').'" /> ';
        $res .= '<input type="submit" class="btn btn-danger" name="profile_delete" value="'.$out_mod->trans('Delete').'" /> ';
        $res .= '<a href="?page=profiles" class="btn btn-secondary">'.$out_mod->trans('Cancel').'</a>';
    }
    else {
        $res .= '<input type="submit" class="btn btn-primary submit_profile" value="'.$out_mod->trans('Create').'" />';
    }
    $res .= '</div></form></div>';

    return $res;

}}
