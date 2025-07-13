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
            Hm_Msgs::add('Could not find public key to remove', 'warning');
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
        if (! check_file_upload($this->request, 'public_key')) {
            return;
        }
        $fingerprint = validate_public_key($this->request->files['public_key']['tmp_name']);
        if (!$fingerprint) {
            Hm_Msgs::add('Unable to import public key', 'danger');
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
        $this->out('html_mail', $this->user_config->get('smtp_compose_type_setting', DEFAULT_SMTP_COMPOSE_TYPE));
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
        if (mb_strpos($text, '----BEGIN PGP MESSAGE-----') !== false) {
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
        $res .= '<div class="container">';
        $res .= '<div class="row justify-content-md-center">';
        $res .= '<div class="col col-lg-8">';
        $res .= '<div class="pgp_section">';

        $res .= '<span class="pgp_sign"><label for="pgp_sign" class="form-label">'.$this->trans('PGP Sign as').'</label>';
        $res .= '<select id="pgp_sign" size="1" class="form-control"></select></span>';

        if (count($pub_keys) > 0) {
            $res .= '<label for="pgp_encrypt" class="form-label" style="margin-top:1rem">'.$this->trans('PGP Encrypt for').
                '</label><select id="pgp_encrypt" size="1" class="form-control"><option disabled selected value=""></option>';
            foreach ($pub_keys as $vals) {
                $res .= '<option value="'.$vals['key'].'">'.$vals['email'].'</option>';
            }
            $res .= '</select>';
        }
        $res .= '</div></div></div></div>';
        return $res;
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_start extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="pgp_settings p-0"><div class="content_title px-3">'.$this->trans('PGP Settings').'</div>';
        $res .= '<script type="text/javascript" src="'.WEB_ROOT.'modules/pgp/assets/openpgp.min.js"></script>';
        return $res;
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_public_keys extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="public_title settings_subtitle p-3 border-bottom"><i class="bi bi-filetype-key me-3"></i> '.$this->trans('Public Keys');
        $res .= '<span class="key_count">'.sprintf($this->trans('%s imported'), count($this->get('pgp_public_keys', array()))).'</span></div>';
        $res .= '<div class="public_keys pgp_block col-lg-7 col-xl-4">';
        $res .= '<form enctype="multipart/form-data" method="post" action="?page=pgp#public_keys" class="pgp_subblock col-lg-6"><div class="mb-2"><label class="form-label">'.$this->trans('Import a public key from a file').'</label><input required id="public_key" name="public_key" type="file" class="form-control"></div>';
        $res .= '<div class="mb-2"><input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
        $res .= '<label class="form-label" for="public_email">For</label>';
        $res .= '<input required id="public_email" name="public_key_email" placeholder="'.$this->trans('E-mail Address');
        $res .= '" type="email" class="form-control warn_on_paste"></div> <input type="submit" value="'.$this->trans('Import').'" class="btn btn-primary">';
        $res .= '</form>';
        $res .= '<table class="pgp_keys table mt-3"><thead><tr><th>'.$this->trans('Fingerprint').'</th>';
        $res .= '<th>'.$this->trans('E-mail').'</th><th></th></tr>';
        $res .= '</thead><tbody>';
        foreach ($this->get('pgp_public_keys', array()) as $index => $vals) {
            $res .= '<tr><td>'.$this->html_safe($vals['fingerprint']).'</td><td>'.$this->html_safe($vals['email']).'</td>';
            $res .= '</td><td><form method="post" action="?page=pgp#public_keys">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="hidden" value="'.$this->html_safe($index).'" name="delete_public_key_id" />'.
                '<button type="submit" class="delete_pgp_key btn btn-light" title="'.$this->trans('Delete').'">';
            $res .= '"<i class="bi bi-x-circle-fill"></i></button></form></td></tr>';
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
        $res = '<div class="priv_title settings_subtitle p-3 border-bottom"><i class="bi bi-key-fill me-3"></i> '.$this->trans('Private Keys');
        $res .= '<span class="private_key_count">'.sprintf($this->trans('%s imported'), 0).'</span></div>';
        $res .= '<div class="priv_keys pgp_block col-lg-7 col-xl-4"><div class="pgp_subblock mb-3">';
        $res .= $this->trans('Private keys never leave your browser, and are deleted when you logout');
        $res .= '<input id="priv_key" type="file" class="form-control">';
        $res .= '</div>'.$this->trans('Existing Keys').'<table class="pgp_keys private_key_list table"><thead><tr><th>'.$this->trans('Identity').'</th><th></th></tr>';
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
        '<div class="pgp_msg_controls input-group"><select class="pgp_private_keys form-control"></select> <input type="button" class="pgp_btn btn-primary" value="Decrypt" /></div>'.prompt_for_passhrase($this);
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_pgp"><a class="unread_link" href="?page=pgp">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-lock-fill account_icon menu-icon"></i> ';
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
        Hm_Debug::add('Gnupg PECL extension not found', 'warning');
        return false;
    }
    if (!is_readable($file_location)) {
        Hm_Debug::add('Uploaded public key not readable', 'warning');
        return false;
    }
    $data = file_get_contents($file_location);
    if (!$data) {
        Hm_Debug::add('Uploaded public key not readable', 'warning');
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
    return '<div class="passphrase_prompt"><div class="title">'.$mod->trans('Please enter your passphrase').'</div><div class="input-group"><input type="password" value="" id="pgp_pass" class="form-control" /> <input id="submit_pgp_pass" type="button" value="'.$mod->trans('Submit').'" class="btn btn-primary" /></div></div>';
}}
