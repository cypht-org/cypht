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
        return '<div class="pgp_settings"><div class="content_title">'.$this->trans('PGP Settings').'</div>';
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_public_keys extends Hm_Output_Module {
    protected function output() {
        return '<div class="settings_subtitle">'.$this->trans('Public Keys').'</div>';
    }
}

/**
 * @subpackage pgp/output
 */
class Hm_Output_pgp_settings_private_key extends Hm_Output_Module {
    protected function output() {
        return '<div class="settings_subtitle">'.$this->trans('Private Keys').'</div>';
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
        $res = '<li class="menu_profiles"><a class="unread_link" href="?page=pgp">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$lock).
            '" alt="" width="16" height="16" /> '.$this->trans('PGP').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

