<?php

/**
 * PGP modules
 * @package modules
 * @subpackage pgp
 */

if (!defined('DEBUG_MODE')) { die(); }


/**
 * @subpackage pgp/handler
 */
class Hm_Handler_load_pgp_data extends Hm_Handler_Module {
    public function process() {
        $this->out('pgp_public_keys', $this->user_config->get('pgp_public_keys', array()));
    }
}

/**
 * @subpackage pgp/handler
 */
class Hm_Handler_pgp_delete_public_key extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('delete_public_key_id'));
        if (!$success) {
            return;
        }
        $keys = $this->user_config->get('pgp_public_keys', array());
        if (!array_key_exists($form['delete_public_key_id'], $keys)) {
            Hm_Msgs::add('ERRCould not find public key to remove');
            return;
        }
        unset($keys[$form['delete_public_key_id']]);
        $this->session->set('pgp_public_keys', $keys, true);
        $this->session->record_unsaved('Public key deleted');
        Hm_Msgs::add('Public key deleted');
    }
}

/**
 * @subpackage pgp/handler
 */
class Hm_Handler_pgp_import_public_key extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('public_key_email'));
        if (!$success) {
            return;
        }
        if (!is_array($this->request->files) || !array_key_exists('public_key', $this->request->files)) {
            return;
        }
        if (!is_array($this->request->files['public_key']) || !array_key_exists('tmp_name', $this->request->files['public_key'])) {
            return;
        }
        $fingerprint = validate_public_key($this->request->files['public_key']['tmp_name']);
        if (!$fingerprint) {
            Hm_Msgs::add('ERRUnable to import public key');
            return;
        }
        $keys = $this->user_config->get('pgp_public_keys', array());
        $keys[] = array('fingerprint' => $fingerprint, 'key' => file_get_contents($this->request->files['public_key']['tmp_name']), 'email' => $form['public_key_email']);
        $this->session->set('pgp_public_keys', $keys, true);
        $this->session->record_unsaved('Public key imported');
        Hm_Msgs::add('Public key imported');
    }
}

/**
 * @subpackage pgp/handler
 */
class Hm_Handler_pgp_compose_data extends Hm_Handler_Module {
    public function process() {
        $this->out('html_mail', $this->user_config->get('smtp_compose_type_setting', 0));
        $this->out('pgp_public_keys', $this->user_config->get('pgp_public_keys', array()));
    }
}

/**
 * @subpackage pgp/handler
 */
class Hm_Handler_pgp_message_check extends Hm_Handler_Module {
    public function process() {
        /* TODO: Check for pgp parts, look at current part for pgp lines */
        $pgp = false;
        $struct = $this->get('msg_struct', array());
        $text = $this->get('msg_text');
        if (strpos($text, '----BEGIN PGP MESSAGE-----') !== false) {
            $pgp = true;
        }
        $part_struct = $this->get('msg_struct_current', array());
        if (is_array($part_struct) && array_key_exists('type', $part_struct) &&
            $part_struct['type'] == 'application' && $part_struct['subtype'] == 'pgp-encrypted') {
            $pgp = true;
        }
        $this->out('pgp_msg_part', $pgp);
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_compose_controls extends Hm_Output_Module {
    protected function output() {
        if ($this->get('html_mail', 0)) {
            return;
        }
        $pub_keys = $this->get('pgp_public_keys', array());
        $res = '<script type="text/javascript" src="'.WEB_ROOT.'modules/pgp/assets/openpgp.min.js"></script>';
        $res .= '<div class="pgp_section">';

        $res .= '<span class="pgp_sign"><label for="pgp_sign">'.$this->trans('PGP Sign as').'</label>';
        $res .= '<select id="pgp_sign" size="1"></select></span>';

        if (count($pub_keys) > 0) {
            $res .= '<label for="pgp_encrypt">'.$this->trans('PGP Encrypt for').
                '</label><select id="pgp_encrypt" size="1"><option disabled selected value=""></option>';
            foreach ($pub_keys as $vals) {
                $res .= '<option value="'.$vals['key'].'">'.$vals['email'].'</option>';
            }
            $res .= '</select>';
        }
        $res .= '<input type="button" class="pgp_apply" value="'.$this->trans('Apply').'" /></div>'.prompt_for_passhrase($this);
        return $res;
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_start extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="pgp_settings"><div class="content_title">'.$this->trans('PGP Settings').'</div>';
        $res .= '<script type="text/javascript" src="'.WEB_ROOT.'modules/pgp/assets/openpgp.min.js"></script>';
        return $res;
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_public_keys extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="public_title settings_subtitle">'.$this->trans('Public Keys');
        $res .= '<span class="key_count">'.sprintf($this->trans('%s imported'), count($this->get('pgp_public_keys', array()))).'</span></div>';
        $res .= '<div class="public_keys pgp_block">';
        $res .= '<form enctype="multipart/form-data" method="post" action="?page=pgp#public_keys" class="pgp_subblock">'.$this->trans('Import a public key from a file').'<br /><br />';
        $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
        $res .= '<input required id="public_key" name="public_key" type="file"> for ';
        $res .= '<input required id="public_email" name="public_key_email" placeholder="'.$this->trans('E-mail Address');
        $res .= '" type="email"> <input type="submit" value="'.$this->trans('Import').'">';
        $res .= '</form>';
        $res .= '<br /><br /><table class="pgp_keys"><thead><tr><th>'.$this->trans('Fingerprint').'</th>';
        $res .= '<th>'.$this->trans('E-mail').'</th><th></th></tr>';
        $res .= '</thead><tbody>';
        foreach ($this->get('pgp_public_keys', array()) as $index => $vals) {
            $res .= '<tr><td>'.$this->html_safe($vals['fingerprint']).'</td><td>'.$this->html_safe($vals['email']).'</td>';
            $res .= '</td><td><form method="post" action="?page=pgp#public_keys">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="hidden" value="'.$this->html_safe($index).'" name="delete_public_key_id" />'.
                '<input type="image" class="delete_pgp_key" alt="'.$this->trans('Delete');
            $res .= '" src="'.Hm_Image_Sources::$circle_x.'"/></button></form></td></tr>';
        }
        $res .= '</tbody></table>';
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_private_key extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="priv_title settings_subtitle">'.$this->trans('Private Keys');
        $res .= '<span class="private_key_count">'.sprintf($this->trans('%s imported'), 0).'</span></div>';
        $res .= '<div class="priv_keys pgp_block"><div class="pgp_subblock">';
        $res .= $this->trans('Private keys never leave your browser, and are deleted when you logout');
        $res .= '<br /><br /><input id="priv_key" type="file">';
        $res .= '</div>'.$this->trans('Existing Keys').'<table class="pgp_keys private_key_list"><thead><tr><th>'.$this->trans('Identity').'</th><th></th></tr>';
        $res .= '</thead><tbody></tbody></table>';
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_end extends Hm_Output_Module {
    protected function output() {
        return '</div>';
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_msg_controls extends Hm_Output_Module {
    protected function output() {
        return '<script type="text/javascript" src="'.WEB_ROOT.'modules/pgp/assets/openpgp.min.js"></script>'.
        '<div class="pgp_msg_controls"><select class="pgp_private_keys"></select> <input type="button" class="pgp_btn" value="Decrypt" /></div>'.prompt_for_passhrase($this);
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=pgp">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$lock).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('PGP').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage pgp/functions
 */
if (!hm_exists('validate_public_key')) {
function validate_public_key($file_location) {
    if (!class_exists('gnupg')) {
        Hm_Debug::add('Gnupg PECL extension not found');
        return false;
    }
    if (!is_readable($file_location)) {
        Hm_Debug::add('Uploaded public key not readable');
        return false;
    }
    $data = file_get_contents($file_location);
    if (!$data) {
        Hm_Debug::add('Uploaded public key not readable');
        return false;
    }
    $tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
    putenv(sprintf('GNUPGHOME=%s/.gnupg', $tmp_dir));
    $gpg = new gnupg();
    $info = $gpg->import($data);
    if (is_array($info) && array_key_exists('fingerprint', $info)) {
        return $info['fingerprint'];
    }
    return false;
}}

/**
 * @subpackage pgp/functions
 */
if (!hm_exists('prompt_for_passhrase')) {
function prompt_for_passhrase($mod) {
    return '<div class="passphrase_prompt"><div class="title">'.$mod->trans('Please enter your passphrase').'</div><input type="password" value="" id="pgp_pass" /> <input id="submit_pgp_pass" type="button" value="'.$mod->trans('Submit').'" /></div>';
}}
