<?php

/**
 * Contact modules
 * @package modules
 * @subpackage contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/contacts/hm-contacts.php';

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_add_contact extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_email', 'contact_name'));
        if ($success) {
        }
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_contacts"><a class="unread_link" href="?page=contacts">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$people).'" alt="" width="16" height="16" /> '.$this->trans('Contacts').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_start extends Hm_Output_Module {
    protected function output() {
        return '<div class="contacts_content"><div class="content_title">'.$this->trans('Contacts').'</div>';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_end extends Hm_Output_Module {
    protected function output() {
        return '</div>';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_add_form extends Hm_Output_Module {
    protected function output() {
        return '<div class="add_server"><div class="server_title">'.$this->trans('Add Contact').'</div>'.
            '<form class="add_contact_form" method="POST">'.
            '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
            '<label class="screen_reader" for="contact_email">'.$this->trans('E-mail Address').'</label>'.
            '<input autofocus required placeholder="'.$this->trans('E-mail Address').'" id="contact_email" type="email" name="contact_email" /><br />'.
            '<label class="screen_reader" class="screen_reader" for="contact_name">'.$this->trans('Full Name').'</label>'.
            '<input placeholder="'.$this->trans('Full Name').'" id="contact_name" type="name" name="contact_name" /><br />'.
            '<input class="add_contact_submit" type="submit" name="add_contact" value="'.$this->trans('Add').'" />'.
            '</form></div>';
    }
}
?>
