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
        $headers = $this->get('http_headers');
        $key_servers = array('https://pgp.mit.edu');
        $key_servers = implode(' ', $key_servers);
        $headers['Content-Security-Policy'] = str_replace('connect-src', 'connect-src '.$key_servers, $headers['Content-Security-Policy']);
        $this->out('http_headers', $headers);
    }
}

/**
 * @subpackage pgp/handler
 */
class Hm_Handler_pgp_compose_data extends Hm_Handler_Module {
    public function process() {
        /* TODO: check for html mail, pgp option (?),  */
        /* load private/public keys */
    }
}

/**
 * @subpackage pgp/handler
 */
class Hm_Handler_pgp_message_check extends Hm_Handler_Module {
    public function process() {
        /* TODO: Check for pgp parts, look at current part for pgp lines */
        $struct = $this->get('msg_struct', array());
        $text = $this->get('msg_text');
        $part_struct = $this->get('msg_struct_current', array());
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_compose_controls extends Hm_Output_Module {
    protected function output() {
        /* TODO: Don't include if html mail is set */
        /* TODO: Don't show encrypt option if there are no public keys */
        /* TODO: Fetch public key list from storage */
        $pub_keys = array(
            array('none', 'None'),
            array('name', 'jason@cypht.org')
        );
        $priv_keys = array(
            array('none', 'None'),
            array('name', 'jason@cypht.org')
        );
        $res = '<script type="text/javascript" src="modules/pgp/assets/openpgp.min.js"></script>'.
            '<div class="pgp_section"><label for="pgp_sign">'.$this->trans('PGP Sign').'</label>'.
            '<select id="pgp_sign" size="1">';
        foreach ($priv_keys as $vals) {
            $res .= '<option value="'.$vals[0].'">'.$vals[1].'</option>';
        }
        $res .= '</select><label for="pgp_encrypt">'.$this->trans('Encrypt for:').
            '</label><select id="pgp_encrypt" size="1">';
        foreach ($pub_keys as $vals) {
            $res .= '<option value="'.$vals[0].'">'.$vals[1].'</option>';
        }
        $res .= '</select></div>';
        return $res;
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_start extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="pgp_settings"><div class="content_title">'.$this->trans('PGP Settings').'</div>';
        $res .= '<script type="text/javascript" src="modules/pgp/assets/openpgp.min.js"></script>';
        return $res;
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_public_keys extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="public_title settings_subtitle">'.$this->trans('Public Keys');
        $res .= '<span class="key_count">'.sprintf($this->trans('%s imported'), 0).'</span></div>';
        $res .= '<div class="public_keys pgp_block">';
        $res .= '<div class="pgp_subblock">'.$this->trans('Import a public key from a file').'<br /><br />';
        $res .= '<input id="public_key" type="file"> for <input id="public_email" placeholder="'.$this->trans('E-mail Address');
        $res .= '" type="email"> <input type="button" value="'.$this->trans('Import').'">';
        $res .= '</div><div class="pgp_subblock">'.$this->trans('Or Search a key server for a key to import').'<br /><br />';
        $res .= '<input id="hkp_email" placeholder="'.$this->trans('E-mail Address').'" type="email" /> <select id="hkp_server">';
        $res .= '<option value="https://pgp.mit.edu">https://pgp.mit.edu</option></select> ';
        $res .= '<input type="button" id="hkp_search" value="'.$this->trans('Search').'" />';
        $res .= '<div class="hkp_search_results"></div>';
        $res .= '</div>'.$this->trans('Existing Keys').'<table class="pgp_keys"><thead><tr><th>'.$this->trans('Key').'</th>';
        $res .= '<th>'.$this->trans('E-mail').'</th></tr>';
        $res .= '</thead><tbody></tbody></table>';
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
        $res .= '<span class="key_count">'.sprintf($this->trans('%s imported'), 0).'</span></div>';
        $res .= '<div class="priv_keys pgp_block"><div class="pgp_subblock">';
        $res .= $this->trans('Private keys never leave your browser, and are deleted when you logout');
        $res .= '<br /><br /><input id="priv_key" type="file"> for <input id="priv_email" placeholder="'.$this->trans('E-mail Address');
        $res .= '" type="email"> <input type="button" value="'.$this->trans('Import').'">';
        $res .= '</div>'.$this->trans('Existing Keys').'<table class="pgp_keys"><thead><tr><th>'.$this->trans('Key').'</th>';
        $res .= '<th>'.$this->trans('E-mail').'</th></tr>';
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

